<?php

declare(strict_types=1);

namespace App\Domain\Payroll\ValueObjects;

use App\Domain\Payroll\Enums\PayrollCorrectionType;
use InvalidArgumentException;

final readonly class PayrollAdjustment
{
    private function __construct(
        public PayrollCorrectionType $type,
        public Money $initialAmount,
        public Money $amount,
        public ?CorrectionComment $comment,
    ) {}

    public static function systemCalculation(Money $initialAmount, Money $newAmount): self
    {
        return new self(
            PayrollCorrectionType::System,
            $initialAmount,
            Money::fromCents($newAmount->cents - $initialAmount->cents),
            null,
        );
    }

    public static function correction(
        Money $initialAmount,
        Money $amount,
        CorrectionComment $comment,
    ): self {
        if ($amount->cents === 0) {
            throw new InvalidArgumentException('A correction amount must not be zero.');
        }

        return new self(PayrollCorrectionType::Adjustment, $initialAmount, $amount, $comment);
    }

    public static function reconstitute(
        PayrollCorrectionType $type,
        Money $initialAmount,
        Money $amount,
        ?CorrectionComment $comment,
    ): self {
        if ($type === PayrollCorrectionType::Adjustment && $amount->cents === 0) {
            throw new InvalidArgumentException('A correction amount must not be zero.');
        }

        return new self($type, $initialAmount, $amount, $comment);
    }
}
