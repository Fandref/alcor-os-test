<?php

declare(strict_types=1);

namespace App\Application\Payroll\Services;

use App\Domain\Payroll\Aggregates\PayrollLine;
use App\Domain\Payroll\Repositories\PayrollLineRepository;
use App\Domain\Payroll\ValueObjects\CorrectionComment;
use App\Domain\Payroll\ValueObjects\Money;

class PayrollService
{
    public function __construct(
        private PayrollLineRepository $payrollLineRepository
    ) {}

    public function createLine(int $systemAmountCents): PayrollLine
    {
        return $this->payrollLineRepository->create(Money::fromCents($systemAmountCents));
    }

    public function recalculateLine(int $lineId, int $systemAmountCents): bool
    {
        return $this->payrollLineRepository->transaction(function () use ($lineId, $systemAmountCents): bool {
            $line = $this->payrollLineRepository->getForUpdate($lineId);

            if (! $line->recalculate(Money::fromCents($systemAmountCents))) {
                return false;
            }

            $this->payrollLineRepository->save($line);

            return true;
        });
    }

    public function addCorrection(int $lineId, int $amountCents, string $comment): PayrollLine
    {
        $amount = Money::fromCents($amountCents);
        $reason = CorrectionComment::fromString($comment);

        return $this->payrollLineRepository->transaction(function () use ($lineId, $amount, $reason): PayrollLine {
            $line = $this->payrollLineRepository->getForUpdate($lineId);
            $line->addCorrection($amount, $reason);
            $this->payrollLineRepository->save($line);

            return $line;
        });
    }
}
