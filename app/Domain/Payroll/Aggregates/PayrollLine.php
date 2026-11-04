<?php

declare(strict_types=1);

namespace App\Domain\Payroll\Aggregates;

use App\Domain\Payroll\Enums\PayrollAuditEntryType;
use App\Domain\Payroll\ValueObjects\CorrectionComment;
use App\Domain\Payroll\ValueObjects\Money;
use App\Domain\Payroll\ValueObjects\PayrollAdjustment;
use App\Domain\Payroll\ValueObjects\PayrollAuditEntry;

class PayrollLine
{
    /** @param list<PayrollAdjustment> $adjustments */
    private function __construct(
        private ?int $id,
        private Money $systemAmount,
        private Money $currentAmount,
        private array $adjustments,
        private int $persistedAdjustmentCount,
    ) {}

    public static function create(Money $systemAmount): self
    {
        return new self(null, $systemAmount, $systemAmount, [], 0);
    }

    /** @param list<PayrollAdjustment> $adjustments */
    public static function reconstitute(
        int $id,
        Money $systemAmount,
        array $adjustments
    ): self {
        $currentAmount = array_reduce(
            $adjustments,
            fn (Money $total, PayrollAdjustment $adjustment): Money => $total->plus($adjustment->amount),
            $systemAmount,
        );

        return new self(
            $id,
            $systemAmount,
            $currentAmount,
            $adjustments,
            count($adjustments)
        );
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function systemAmount(): Money
    {
        return $this->systemAmount;
    }

    /** @return list<PayrollAdjustment> */
    public function adjustments(): array
    {
        return $this->adjustments;
    }

    public function recalculate(Money $newSystemAmount): bool
    {
        if ($this->adjustments !== []) {
            return false;
        }

        $this->systemAmount = $newSystemAmount;
        $this->currentAmount = $newSystemAmount;

        return true;
    }

    public function addCorrection(Money $amount, CorrectionComment $comment): void
    {
        $this->adjustments[] = PayrollAdjustment::create($amount, $comment);
        $this->currentAmount = $this->currentAmount->plus($amount);
    }

    public function currentAmount(): Money
    {
        return $this->currentAmount;
    }

    /** @return list<PayrollAuditEntry> */
    public function auditHistory(): array
    {
        $value = $this->systemAmount;
        $history = [new PayrollAuditEntry(PayrollAuditEntryType::System, $this->systemAmount, null, $value)];

        foreach ($this->adjustments as $adjustment) {
            $value = $value->plus($adjustment->amount);
            $history[] = new PayrollAuditEntry(
                PayrollAuditEntryType::Adjustment,
                $adjustment->amount,
                $adjustment->comment,
                $value,
            );
        }

        $history[] = new PayrollAuditEntry(PayrollAuditEntryType::Current, $value, null, $value);

        return $history;
    }

    /** @return list<PayrollAdjustment> */
    public function pendingAdjustments(): array
    {
        return array_slice($this->adjustments, $this->persistedAdjustmentCount);
    }

    public function markPersisted(int $id): void
    {
        $this->id = $id;
        $this->persistedAdjustmentCount = count($this->adjustments);
    }
}
