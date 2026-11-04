# Alcor Payroll Domain Model

This proof of concept models a system-calculated payroll line and the specialist corrections applied to it. It uses a small DDD structure: a domain aggregate owns payroll rules, `PayrollService` coordinates application operations, and a repository port separates the domain from Laravel's Eloquent persistence.

## How It Works

- `PayrollLine` is the aggregate root. It stores the system amount, ordered adjustments, and current amount in memory.
- `Money` represents amounts as integer cents. This avoids floating-point arithmetic: `$1,050.00` is passed as `105000`.
- `CorrectionComment` trims a comment and rejects an empty value. `PayrollAdjustment` rejects a zero amount; positive and negative amounts are accepted.
- A system recalculation replaces the current system amount only while the line has no corrections. After the first correction, recalculation returns `false` and leaves the frozen system amount unchanged.
- The current amount is the frozen system amount plus all correction amounts. It is calculated by the aggregate and is not stored as a separate database value.
- `auditHistory()` returns the system baseline, every correction with its comment and resulting running value, followed by the current total.
- Corrections have no edit or delete operation in the domain API. A mistaken correction is offset by adding another correction.

## Architecture

- `app/Domain/Payroll` contains the aggregate, value objects, audit entry type, and `PayrollLineRepository` interface. It does not depend on Laravel.
- `app/Application/Payroll/Services/PayrollService.php` exposes `createLine()`, `recalculateLine()`, and `addCorrection()`. It coordinates the aggregate and repository in transactions for updates.
- `app/Infrastructure/Persistence/Eloquent` implements the repository and maps database records to and from the domain aggregate. Corrections are loaded in ascending database ID order; only pending corrections are inserted when saving.
- `AppServiceProvider` binds the repository interface to `EloquentPayrollLineRepository`.

## Database Model

- `payroll_lines` stores the system baseline in `system_amount_cents`.
- `payroll_corrections` stores one row per adjustment: `payroll_line_id`, signed `amount_cents`, and required-by-domain `comment`.
- The foreign key uses `RESTRICT` on parent deletion so a line with correction rows cannot be deleted while preserving those rows.
- The current total is derived from the baseline and correction history, not duplicated in the database.

## Usage

Use the application service instead of writing Eloquent records directly:

```php
use App\Application\Payroll\Services\PayrollService;
use App\Domain\Payroll\Repositories\PayrollLineRepository;

$payroll = app(PayrollService::class);

$line = $payroll->createLine(100000); // $1,000.00 system amount
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

- Amounts passed to `PayrollService` are integer cents. The current schema also stores integer cents; it does not use a `DECIMAL` column.
- A payroll line is created with an initial system amount. `recalculateLine()` receives the full replacement amount, not a delta.
- The first manual correction freezes the last accepted system amount. Any later source-data changes are intentionally ignored for that line.
- This proof of concept assumes one currency and does not model employees, payroll periods, authorization, or currency conversion.
- Append-only behavior is enforced by the supported domain/service API, not by database permissions or triggers. Direct SQL or direct Eloquent writes can bypass the domain rules; a production system should restrict those writes or add database-level enforcement if tamper-proof audit history is required.
- The correction comment is required by the domain service, but the schema itself does not declare a non-empty check constraint.
