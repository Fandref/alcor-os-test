<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Payroll\Services\PayrollService;
use App\Domain\Payroll\Enums\PayrollCorrectionType;
use App\Domain\Payroll\Repositories\PayrollLineRepository;
use App\Infrastructure\Persistence\Eloquent\Models\PayrollCorrectionRecord;
use App\Infrastructure\Persistence\Eloquent\Models\PayrollLineRecord;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class PayrollServicesTest extends TestCase
{
    use RefreshDatabase;

    public function test_services_persist_a_frozen_line(): void
    {
        $payroll = app(PayrollService::class);
        $line = $payroll->createLine(100000);
        $this->assertTrue($payroll->recalculateLine($line->id(), 105000));

        $payroll->addCorrection($line->id(), -4555, 'Employee declined dental benefit; reversing deduction');
        $this->assertFalse($payroll->recalculateLine($line->id(), 20000));
        $payroll->addCorrection($line->id(), 10010, 'Late correction: missed approved overtime bonus');
        $payroll->addCorrection($line->id(), -10, 'Minor rounding adjustment');
        $payroll->addCorrection($line->id(), -20, 'Second minor rounding adjustment');
        $payroll->addCorrection($line->id(), 20, 'Correcting mistake in adjustment #4');

        $persistedLine = app(PayrollLineRepository::class)->find($line->id());

        $this->assertSame(110445, $persistedLine->currentAmount()->cents);
        $this->assertSame(105000, $persistedLine->systemAmount()->cents);
        $this->assertSame(5, count($persistedLine->adjustments()));

        $lineRecord = PayrollLineRecord::query()->findOrFail($line->id());
        $this->assertSame(110445, $lineRecord->current_amount_cents);

        $history = PayrollCorrectionRecord::query()->where('payroll_line_id', $line->id())->orderBy('id')->get();
        $this->assertCount(7, $history);
        $this->assertSame(PayrollCorrectionType::System, $history[0]->type);
        $this->assertSame(0, $history[0]->initial_amount_cents);
        $this->assertSame(100000, $history[0]->amount_cents);
        $this->assertSame(PayrollCorrectionType::System, $history[1]->type);
        $this->assertSame(100000, $history[1]->initial_amount_cents);
        $this->assertSame(5000, $history[1]->amount_cents);
        $this->assertSame(PayrollCorrectionType::Adjustment, $history[2]->type);
        $this->assertSame(105000, $history[2]->initial_amount_cents);
        $this->assertSame(-4555, $history[2]->amount_cents);
    }

    public function test_service_rejects_empty_comments_and_zero_adjustments(): void
    {
        $payroll = app(PayrollService::class);
        $line = $payroll->createLine(100000);

        try {
            $payroll->addCorrection($line->id(), 10, '   ');
            $this->fail('A blank comment must be rejected.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('A correction comment is required.', $exception->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        $payroll->addCorrection($line->id(), 0, 'No amount');
    }

    public function test_database_restricts_deleting_a_line_with_corrections(): void
    {
        $payroll = app(PayrollService::class);
        $line = $payroll->createLine(100000);
        $payroll->addCorrection($line->id(), 100, 'Approved adjustment');
        $record = PayrollLineRecord::query()->findOrFail($line->id());

        $this->expectException(QueryException::class);
        $record->delete();
    }

    public function test_audit_history(): void
    {
        $payroll = app(PayrollService::class);
        $line = $payroll->createLine(105000);
        $payroll->addCorrection($line->id(), -4555, 'Benefit reversal');
        $payroll->addCorrection($line->id(), 10010, 'Late overtime');
        $payroll->addCorrection($line->id(), -10, 'Rounding');
        $payroll->addCorrection($line->id(), -20, 'Rounding fix');
        $line = $payroll->addCorrection($line->id(), 20, 'Reverse prior mistake');

        $historyMapper = fn($entry) => [
            $entry->valueAfter->cents,
            $entry->amount->cents,
            $entry->type,
            $entry->comment?->value,
        ];

        $history = array_map($historyMapper, $line->auditHistory());

        $persistedHistory = array_map(
            $historyMapper,
            app(PayrollLineRepository::class)->find($line->id())->auditHistory()
        );

        $this->assertEqualsCanonicalizing(
            $history,
            $persistedHistory,
        );
    }
}
