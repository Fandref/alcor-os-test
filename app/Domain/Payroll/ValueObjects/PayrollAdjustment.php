<?php

declare(strict_types=1);

namespace App\Domain\Payroll\ValueObjects;

use InvalidArgumentException;

final readonly class PayrollAdjustment
{
    private function __construct(
        public Money $amount,
        public CorrectionComment $comment,
    ) {}

    public static function create(Money $amount, CorrectionComment $comment): self
    {
        if ($amount->cents === 0) {
            throw new InvalidArgumentException('A correction amount must not be zero.');
        }

        return new self($amount, $comment);
    }
}
