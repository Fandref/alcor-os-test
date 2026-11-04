<?php

declare(strict_types=1);

namespace App\Domain\Payroll\Enums;

enum PayrollAuditEntryType: string
{
    case System = 'system';
    case Adjustment = 'adjustment';
    case Current = 'current';

    /**
     * @param PayrollCorrectionType $type The PayrollCorrectionType to map.
     * @return self The corresponding PayrollAuditEntryType.
     * @throws \InvalidArgumentException If the provided PayrollCorrectionType is invalid.
     */
    public static function correctionType(PayrollCorrectionType $type): self
    {
        return match ($type) {
            PayrollCorrectionType::System => self::System,
            PayrollCorrectionType::Adjustment => self::Adjustment,
            default => throw new \InvalidArgumentException('Invalid PayrollCorrectionType: ' . $type->value),
        };
    }
}
