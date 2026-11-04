<?php

declare(strict_types=1);

namespace App\Domain\Payroll\ValueObjects;

use InvalidArgumentException;

final readonly class CorrectionComment
{
    private function __construct(public string $value) {}

    public static function fromString(string $comment): self
    {
        $comment = trim($comment);

        if ($comment === '') {
            throw new InvalidArgumentException('A correction comment is required.');
        }

        return new self($comment);
    }
}
