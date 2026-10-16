<?php

namespace Tests\Unit;

use App\Domain\Shared\Proportion;
use PHPUnit\Framework\TestCase;

class InventoryAllocationTest extends TestCase
{
    public function test_rounding_partial_and_last_weight_preserve_total_without_float_overflow(): void
    {
        $this->assertSame(240000, Proportion::round(3000000, 80000, 1000000));
        $this->assertSame(90000, Proportion::round(240000, 30000, 80000));
        $this->assertSame(1, Proportion::round(1, 1, 2));
        $remaining = 100000000000001;
        $grams = 999999999;
        $released = Proportion::round($remaining, 333333333, $grams);
        $this->assertSame(33333333333334, $released);
        $this->assertSame($remaining, $released + Proportion::round($remaining - $released, $grams - 333333333, $grams - 333333333));
    }
}
