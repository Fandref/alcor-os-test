<?php

declare(strict_types=1);

namespace App\Domain\Payroll\ValueObjects;

final readonly class Money
{
    private function __construct(public int $cents) {}

    public static function fromCents(int $cents): self
    {
        return new self($cents);
    }

    public function plus(self $other): self
    {
        return new self($this->cents + $other->cents);
    }
}
