<?php

namespace App\Domain\Payroll;

class LoadingWageAllocator
{
    public function workers(int $total, array $ids): array
    {
        sort($ids, SORT_NUMERIC);
        $count = count($ids);
        if (! $count) {
            return [];
        }

        return array_map(fn ($id, $index) => ['worker_id' => $id, 'member_count_snapshot' => $count,
            'share_amount_idr' => intdiv($total, $count) + ($index < $total % $count ? 1 : 0),
            'allocation_order' => $index + 1], $ids, array_keys($ids));
    }

    public function categories(int $total, array $weights): array
    {
        $basis = array_sum($weights);
        $allocated = [];
        $remainders = [];
        foreach ($weights as $type => $grams) {
            $product = ($total % $basis) * $grams;
            $allocated[$type] = intdiv($total, $basis) * $grams + intdiv($product, $basis);
            $remainders[$type] = $product % $basis;
        }
        // Sisa terbesar dahulu; saat sama, SORT sebelum TBS secara leksikografis.
        uksort($remainders, fn ($a, $b) => ($remainders[$b] <=> $remainders[$a]) ?: strcmp($a, $b));
        $left = $total - array_sum($allocated);
        foreach (array_keys($remainders) as $type) {
            if ($left-- > 0) {
                $allocated[$type]++;
            }
        }

        return $allocated;
    }
}
