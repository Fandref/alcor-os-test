# Alcor Payroll Domain Model

This proof of concept models a system-calculated payroll line and the specialist corrections applied to it. It uses a small DDD structure: a domain aggregate owns payroll rules, `PayrollService` coordinates application operations, and a repository port separates the domain from Laravel's Eloquent persistence.

## How It Works

- `PayrollLine` is the aggregate root. It tracks the latest accepted system amount, ordered history events, and current total in memory.
- `Money` represents amounts as integer cents. This avoids floating-point arithmetic: `$1,050.00` is passed as `105000`.
- `CorrectionComment` trims a comment and rejects an empty value. `PayrollAdjustment` rejects a zero amount; positive and negative amounts are accepted.
- A system recalculation updates the line while there are no manual adjustments and appends a `system` history event. After the first manual adjustment, recalculation returns `false` and does not change the stored total.
- A manual adjustment appends an `adjustment` event and updates the line's current total in the same transaction.
- `auditHistory()` returns system and adjustment events in insertion order. Each event records its type, amount before the event, signed delta, and comment; the audit view also reports the resulting total.
- Corrections have no edit or delete operation in the domain API. A mistaken correction is offset by adding another correction.

## Architecture

- `app/Domain/Payroll` contains the aggregate, value objects, audit entry type, and `PayrollLineRepository` interface. It does not depend on Laravel.
- `app/Application/Payroll/Services/PayrollService.php` exposes `createLine()`, `recalculateLine()`, and `addCorrection()`. It coordinates the aggregate and repository in transactions for updates.
- `app/Infrastructure/Persistence/Eloquent` implements the repository and maps database records to and from the domain aggregate. History events are loaded in ascending database ID order; only events added since loading are inserted when saving.
- `AppServiceProvider` binds the repository interface to `EloquentPayrollLineRepository`.

## Database Model

- `payroll_lines.current_amount_cents` stores the current final total.
- `payroll_corrections` stores one row per history event: `payroll_line_id`, `type` (`system` or `adjustment`), `initial_amount_cents` (the total before the event), signed `amount_cents` (the event delta), and `comment`.
- The foreign key uses `RESTRICT` on parent deletion so a line with correction rows cannot be deleted while preserving those rows.
- On creation, the initial system value is recorded as a `system` event from zero. Each accepted system recalculation is another `system` event. Manual changes are stored as `adjustment` events.

## Usage

Use the application service instead of writing Eloquent records directly:

```php
use App\Application\Payroll\Services\PayrollService;
use App\Domain\Payroll\Repositories\PayrollLineRepository;

$payroll = app(PayrollService::class);

$line = $payroll->createLine(100000); // $1,000.00 current amount and initial system event
$payroll->recalculateLine($line->id(), 105000); // $1,050.00 before corrections

$line = $payroll->addCorrection(
    $line->id(),
    -4555,
    'Employee declined dental benefit; reversing deduction',
);

$payroll->recalculateLine($line->id(), 120000); // false; the baseline is frozen
$line = $payroll->addCorrection($line->id(), 10010, 'Late correction: missed approved overtime bonus');

$currentCents = $line->currentAmount()->cents; // 110455
$audit = $line->auditHistory();

$reloadedLine = app(PayrollLineRepository::class)->find($line->id());
```

## Run

```sh
php artisan migrate
php artisan test
```

Feature tests use an in-memory SQLite database and `RefreshDatabase` to run the migrations. Unit tests exercise the aggregate without Laravel or a database. The tests cover recalculation/freeze behavior, correction totals and audit history, comment/amount validation, persistence after reload, and the database restriction on deleting a line with corrections.

## Assumptions And Limitations

- Amounts passed to `PayrollService` and stored in the database are integer cents; this schema does not use `DECIMAL` columns.
- A payroll line is created with an initial system amount. `recalculateLine()` receives the full replacement amount, not a delta.
- System recalculations are retained as history events until the first manual adjustment. That adjustment freezes the latest accepted system amount; later source-data changes are ignored for the line.
- This proof of concept assumes one currency and does not model employees, payroll periods, authorization, or currency conversion.
- Append-only behavior is enforced by the supported domain/service API, not by database permissions or triggers. Direct SQL or direct Eloquent writes can bypass the domain rules; a production system should restrict those writes or add database-level enforcement if tamper-proof audit history is required.
- Every history row, including a system event, receives a comment; system rows use the fixed comment `System calculation`.
- The schema does not enforce a non-empty comment or non-zero adjustment with check constraints; those rules are checked by domain value objects.
