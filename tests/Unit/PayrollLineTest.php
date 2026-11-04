<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Payroll\Aggregates\PayrollLine;
use App\Domain\Payroll\Enums\PayrollAuditEntryType;
use App\Domain\Payroll\ValueObjects\CorrectionComment;
use App\Domain\Payroll\ValueObjects\Money;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class PayrollLineTest extends TestCase
{
    public function test_system_recalculation_stops_after_first_correction(): void
    {
        $line = PayrollLine::create(Money::fromCents(1000000));

        $this->assertTrue($line->recalculate(Money::fromCents(105000)));
        $line->addCorrection(
            Money::fromCents(-4555),
            CorrectionComment::fromString('Benefit reversal'),
        );

        $this->assertFalse($line->recalculate(Money::fromCents(120000)));
        $this->assertSame(105000, $line->systemAmount()->cents);
        $this->assertSame(100445, $line->currentAmount()->cents);
    }

    public function test_audit_history_contains_system_value_adjustments_and_current_value(): void
    {
        $line = PayrollLine::create(Money::fromCents(105000));
        $line->addCorrection(Money::fromCents(-4555), CorrectionComment::fromString('Benefit reversal'));
        $line->addCorrection(Money::fromCents(10010), CorrectionComment::fromString('Late overtime'));
        $line->addCorrection(Money::fromCents(-10), CorrectionComment::fromString('Rounding'));
        $line->addCorrection(Money::fromCents(-20), CorrectionComment::fromString('Rounding fix'));
        $line->addCorrection(Money::fromCents(20), CorrectionComment::fromString('Reverse prior mistake'));

        $history = $line->auditHistory();

        $this->assertSame(
            [105000, -4555, 10010, -10, -20, 20, 110445],
            array_map(fn ($entry): int => $entry->amount->cents, $history),
        );
        $this->assertSame(
            [105000, 100445, 110455, 110445, 110425, 110445, 110445],
            array_map(fn ($entry): int => $entry->valueAfter->cents, $history),
        );
        $this->assertSame(PayrollAuditEntryType::System, $history[0]->type);
    }

    public function test_zero_adjustment_and_blank_comment_are_rejected(): void
    {
        $line = PayrollLine::create(Money::fromCents(100_000));

        try {
            $line->addCorrection(Money::fromCents(0), CorrectionComment::fromString('No amount'));
            $this->fail('A zero correction must be rejected.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('A correction amount must not be zero.', $exception->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        CorrectionComment::fromString('   ');
    }
}
