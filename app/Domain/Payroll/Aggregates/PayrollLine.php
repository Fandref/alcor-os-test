<?php

declare(strict_types=1);

namespace App\Domain\Payroll\Aggregates;

use App\Domain\Payroll\Enums\PayrollAuditEntryType;
use App\Domain\Payroll\Enums\PayrollCorrectionType;
use App\Domain\Payroll\ValueObjects\CorrectionComment;
use App\Domain\Payroll\ValueObjects\Money;
use App\Domain\Payroll\ValueObjects\PayrollAdjustment;
use App\Domain\Payroll\ValueObjects\PayrollAuditEntry;

class PayrollLine
{
    private Money $systemAmount;

    /** @param list<PayrollAdjustment> $historyEntries */
    private function __construct(
        private ?int $id,
        private Money $currentAmount,
        private array $historyEntries,
        private int $persistedHistoryCount,
    ) {
        $latestSystemEntry = collect($historyEntries)
            ->last(fn(PayrollAdjustment $entry): bool => $entry->type === PayrollCorrectionType::System);

        $this->systemAmount = $latestSystemEntry?->initialAmount->cents === 0
            ? $currentAmount
            : $latestSystemEntry->initialAmount;
    }

    public static function create(Money $systemAmount): self
    {
        return new self(
            null,
            $systemAmount,
            [PayrollAdjustment::systemCalculation(Money::fromCents(0), $systemAmount)],
            0,
        );
    }

    /** @param list<PayrollAdjustment> $historyEntries */
    public static function reconstitute(
        int $id,
        Money $currentAmount,
        array $historyEntries,
    ): self {
        return new self(
            $id,
            $currentAmount,
            $historyEntries,
            count($historyEntries),
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

    /** @return list<PayrollAdjustment> Manual adjustments only. */
    public function adjustments(): array
    {
        return array_values(array_filter(
            $this->historyEntries,
            fn(PayrollAdjustment $entry): bool => $entry->type === PayrollCorrectionType::Adjustment,
        ));
    }

    public function recalculate(Money $newSystemAmount): bool
    {
        if ($this->adjustments() !== []) {
            return false;
        }

        $previousAmount = $this->currentAmount;
        $this->systemAmount = $newSystemAmount;
        $this->currentAmount = $newSystemAmount;
        $this->historyEntries[] = PayrollAdjustment::systemCalculation($previousAmount, $newSystemAmount);

        return true;
    }

    public function addCorrection(Money $amount, CorrectionComment $comment): void
    {
        $this->historyEntries[] = PayrollAdjustment::correction($this->currentAmount, $amount, $comment);
        $this->currentAmount = $this->currentAmount->plus($amount);
    }

    public function currentAmount(): Money
    {
        return $this->currentAmount;
    }

    /** @return list<PayrollAuditEntry> */
    public function auditHistory(): array
    {
        $history = [];

        foreach ($this->historyEntries as $entry) {
            $value = $entry->initialAmount->plus($entry->amount);
            $history[] = new PayrollAuditEntry(
                PayrollAuditEntryType::correctionType($entry->type),
                $entry->amount,
                $entry->comment,
                $value,
            );
        }

        return $history;
    }

    /** @return list<PayrollAdjustment> */
    public function pendingHistoryEntries(): array
    {
        return array_slice($this->historyEntries, $this->persistedHistoryCount);
    }

    public function markPersisted(int $id): void
    {
        $this->id = $id;
        $this->persistedHistoryCount = count($this->historyEntries);
    }
}
