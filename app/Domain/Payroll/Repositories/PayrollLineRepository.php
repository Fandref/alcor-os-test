<?php

declare(strict_types=1);

namespace App\Domain\Payroll\Repositories;

use App\Domain\Payroll\Aggregates\PayrollLine;
use App\Domain\Payroll\ValueObjects\Money;
use Closure;

interface PayrollLineRepository
{
    public function transaction(Closure $operation): mixed;

    public function create(Money $systemAmount): PayrollLine;

    public function find(int $id): PayrollLine;

    public function getForUpdate(int $id): PayrollLine;

    public function save(PayrollLine $line): void;
}
