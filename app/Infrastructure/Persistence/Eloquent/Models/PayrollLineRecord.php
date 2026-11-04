<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $current_amount_cents
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
#[Table('payroll_lines')]
#[Fillable(['current_amount_cents'])]
class PayrollLineRecord extends Model
{
    protected function casts(): array
    {
        return [
            'current_amount_cents' => 'integer',
        ];
    }

    public function corrections(): HasMany
    {
        return $this->hasMany(PayrollCorrectionRecord::class, 'payroll_line_id')->orderBy('id');
    }
}
