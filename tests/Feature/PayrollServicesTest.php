<?php

namespace Tests\Feature;

use App\Application\Payroll\Services\PayrollService;
use App\Domain\Payroll\Enums\PayrollAuditEntryType;
use App\Domain\Payroll\Repositories\PayrollLineRepository;
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
