<?php

declare(strict_types=1);

namespace App\Domain\Payroll\Enums;

enum PayrollCorrectionType: string
{
    case System = 'system';
    case Adjustment = 'adjustment';
}
