<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use App\Domain\Payroll\Enums\PayrollCorrectionType;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $payroll_line_id
 * @property PayrollCorrectionType $type
 * @property int $initial_amount_cents
 * @property int $amount_cents
 * @property string|null $comment
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
#[Table('payroll_corrections')]
#[Fillable(['payroll_line_id', 'type', 'initial_amount_cents', 'amount_cents', 'comment'])]
class PayrollCorrectionRecord extends Model
{
    protected function casts(): array
    {
        return [
            'type' => PayrollCorrectionType::class,
            'initial_amount_cents' => 'integer',
            'amount_cents' => 'integer',
        ];
    }

    public function payrollLine(): BelongsTo
    {
        return $this->belongsTo(PayrollLineRecord::class, 'payroll_line_id');
    }
}
