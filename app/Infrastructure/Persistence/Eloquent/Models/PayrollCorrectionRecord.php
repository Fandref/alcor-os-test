<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollCorrectionRecord extends Model
{
    protected $table = 'payroll_corrections';

    protected $fillable = ['payroll_line_id', 'amount_cents', 'comment'];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
        ];
    }

    public function payrollLine(): BelongsTo
    {
        return $this->belongsTo(PayrollLineRecord::class, 'payroll_line_id');
    }
}
