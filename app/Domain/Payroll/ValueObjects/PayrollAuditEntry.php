<?php

declare(strict_types=1);

namespace App\Domain\Payroll\ValueObjects;

use App\Domain\Payroll\Enums\PayrollAuditEntryType;

final readonly class PayrollAuditEntry
{
    public function __construct(
        public PayrollAuditEntryType $type,
        public Money $amount,
        public ?CorrectionComment $comment,
        public Money $valueAfter,
    ) {}
}
