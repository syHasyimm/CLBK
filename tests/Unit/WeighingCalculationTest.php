<?php

namespace Tests\Unit;

use App\Domain\Payroll\LoadingWageAllocator;
use App\Domain\Shared\Money;
use App\Domain\Shared\Weight;
use App\Domain\Weighings\WeighingCalculator;
use App\Models\PriceSchedule;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class WeighingCalculationTest extends TestCase
{
    public function test_customer_proceeds_are_not_reduced_and_workers_share_deterministically(): void
    {
        $price = new PriceSchedule(['tbs_buy_price_idr' => 3000, 'sort_buy_price_idr' => 2000, 'loading_rate_idr' => 100]);
        $calculator = new WeighingCalculator;
        foreach ([1, 2, 3] as $count) {
            $result = $calculator->calculate(['TBS' => 1000000, 'SORT' => 100000], array_reverse(range(1, $count)), $price);
            $this->assertSame(3200000, $result['total_purchase_idr']);
            $this->assertSame(110000, $result['total_loading_wage_idr']);
            $this->assertSame(range(1, $count), array_column($result['loaders'], 'worker_id'));
            $this->assertSame(110000, array_sum(array_column($result['loaders'], 'share_amount_idr')));
            $this->assertSame(110000, array_sum(array_column($result['items'], 'loading_cost_allocated_idr')));
        }
        $this->assertSame([33334, 33333, 33333], array_column((new LoadingWageAllocator)->workers(100000, [3, 1, 2]), 'share_amount_idr'));
    }

    public function test_ac37_rounding_allocates_the_single_rupiah_once(): void
    {
        $result = (new WeighingCalculator)->calculate(['TBS' => 5, 'SORT' => 5], [7, 2],
            new PriceSchedule(['tbs_buy_price_idr' => 3000, 'sort_buy_price_idr' => 2000, 'loading_rate_idr' => 100]));
        $this->assertSame(1, $result['total_loading_wage_idr']);
        $this->assertSame(1, array_sum(array_column($result['items'], 'loading_cost_allocated_idr')));
        $this->assertSame([1, 0], array_column($result['loaders'], 'share_amount_idr'));
    }

    public function test_large_values_and_many_partitions_reconcile_without_float_overflow(): void
    {
        $allocator = new LoadingWageAllocator;
        foreach ([[1000000000, 1000000000], [999999999, 1], [5, 6], [19, 333], [1, 1]] as [$tbs, $sort]) {
            foreach ([0, 1, 100, 100000000] as $rate) {
                $wage = Money::forWeight($tbs + $sort, $rate);
                $costs = $allocator->categories($wage, ['TBS' => $tbs, 'SORT' => $sort]);
                $this->assertSame($wage, array_sum($costs));
                $this->assertIsInt($costs['TBS']);
                $this->assertIsInt($costs['SORT']);
                $this->assertSame($wage, array_sum(array_column($allocator->workers($wage, [3, 2, 1]), 'share_amount_idr')));
            }
        }
    }

    public function test_weight_conversion_is_exact(): void
    {
        $this->assertSame(1250500, Weight::grams('1250,500', 'weight'));
        $this->assertSame(5, Weight::grams('0.005', 'weight'));
        $this->assertSame(1000000000, Weight::grams('1000000', 'weight'));
        $this->assertSame('1250.500', Weight::kg(1250500));
    }

    public function test_subgram_and_ambiguous_weight_input_are_rejected(): void
    {
        $this->expectException(ValidationException::class);
        Weight::grams('1.2345', 'weight');
    }
}
