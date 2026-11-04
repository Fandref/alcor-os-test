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
        $record = PayrollLineRecord::create(['system_amount_cents' => $systemAmount->cents]);

        return PayrollLine::reconstitute($record->id, $systemAmount, []);
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
        $record->system_amount_cents = $line->systemAmount()->cents;
        $record->save();

        foreach ($line->pendingAdjustments() as $adjustment) {
            $record->corrections()->create([
                'amount_cents' => $adjustment->amount->cents,
                'comment' => $adjustment->comment->value,
            ]);
        }

        $line->markPersisted($record->id);
    }

    private function toAggregate(PayrollLineRecord $record): PayrollLine
    {
        $adjustments = $record->corrections
            ->map(fn(PayrollCorrectionRecord $correction): PayrollAdjustment => PayrollAdjustment::create(
                Money::fromCents($correction->amount_cents),
                CorrectionComment::fromString($correction->comment),
            ))
            ->all();

        return PayrollLine::reconstitute(
            $record->id,
            Money::fromCents($record->system_amount_cents),
            $adjustments,
        );
    }
}
