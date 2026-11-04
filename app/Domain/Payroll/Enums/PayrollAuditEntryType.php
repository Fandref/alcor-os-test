<?php

namespace App\Domain\Payroll\Enums;

enum PayrollAuditEntryType: string
{
    case System = 'system';
    case Adjustment = 'adjustment';
    case Current = 'current';
}
