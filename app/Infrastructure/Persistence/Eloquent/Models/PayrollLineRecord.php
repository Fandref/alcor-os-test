<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollLineRecord extends Model
{
    protected $table = 'payroll_lines';

    protected $fillable = ['system_amount_cents'];

    protected function casts(): array
    {
        return [
            'system_amount_cents' => 'integer',
        ];
    }

    public function corrections(): HasMany
    {
        return $this->hasMany(PayrollCorrectionRecord::class, 'payroll_line_id')->orderBy('id');
    }
}
