<?php

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Payroll\Aggregates\PayrollLine;
use App\Domain\Payroll\Repositories\PayrollLineRepository;
use App\Domain\Payroll\ValueObjects\CorrectionComment;
use App\Domain\Payroll\ValueObjects\Money;
use App\Domain\Payroll\ValueObjects\PayrollAdjustment;
use App\Infrastructure\Persistence\Eloquent\Models\PayrollCorrectionRecord;
use App\Infrastructure\Persistence\Eloquent\Models\PayrollLineRecord;
use Closure;
use Illuminate\Support\Facades\DB;
use LogicException;

class EloquentPayrollLineRepository implements PayrollLineRepository
{
    public function transaction(Closure $operation): mixed
    {
        return DB::transaction($operation);
    }

    public function create(Money $systemAmount): PayrollLine
    {
        return DB::transaction(function () use ($systemAmount): PayrollLine {
            $record = PayrollLineRecord::create(['current_amount_cents' => $systemAmount->cents]);
            $line = PayrollLine::create($systemAmount);

            $this->persistHistoryEntries($record, $line->pendingHistoryEntries());
            $line->markPersisted($record->id);

            return $line;
        });
    }

    public function find(int $id): PayrollLine
    {
        return $this->toAggregate(
            PayrollLineRecord::query()->with('corrections')->findOrFail($id)
        );
    }

    public function getForUpdate(int $id): PayrollLine
    {
        $record = PayrollLineRecord::query()->with('corrections')->lockForUpdate()->findOrFail($id);

        return $this->toAggregate($record);
    }

    public function save(PayrollLine $line): void
    {
        if ($line->id() === null) {
            throw new LogicException('A payroll line must be created before it can be saved.');
        }

        $record = PayrollLineRecord::query()->findOrFail($line->id());
        $record->current_amount_cents = $line->currentAmount()->cents;
        $record->save();

        $this->persistHistoryEntries($record, $line->pendingHistoryEntries());

        $line->markPersisted($record->id);
    }

    /** @param list<PayrollAdjustment> $entries */
    private function persistHistoryEntries(PayrollLineRecord $record, array $entries): void
    {
        foreach ($entries as $entry) {
            $record->corrections()->create([
                'type' => $entry->type,
                'initial_amount_cents' => $entry->initialAmount->cents,
                'amount_cents' => $entry->amount->cents,
                'comment' => $entry->comment?->value,
            ]);
        }
    }

    private function toAggregate(PayrollLineRecord $record): PayrollLine
    {
        $adjustments = $record->corrections
            ->map(fn (PayrollCorrectionRecord $correction): PayrollAdjustment => PayrollAdjustment::reconstitute(
                $correction->type,
                Money::fromCents($correction->initial_amount_cents),
                Money::fromCents($correction->amount_cents),
                $correction->comment ? CorrectionComment::fromString($correction->comment) : null,
            ))
            ->all();

        return PayrollLine::reconstitute(
            $record->id,
            Money::fromCents($record->current_amount_cents),
            $adjustments,
        );
    }
}
