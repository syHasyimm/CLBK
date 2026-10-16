<?php

namespace App\Services;

use App\Support\Database\ReadSnapshot;

final class BackupSnapshot
{
    private const RUNTIME = ['sessions', 'password_reset_tokens', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'database_write_locks'];

    public function inspect(?string $file = null): array
    {
        if ($file === null) {
            return app(ReadSnapshot::class)->run(fn () => $this->summarize(app(MySqlBackupData::class)->live()));
        }

        return $this->summarize(app(MySqlBackupData::class)->read($file));
    }

    private function summarize(iterable $records): array
    {
        $tables = $proofs = $migrations = $posted = $allocations = [];
        $metrics = ['cash_idr' => 0, 'customer_debt_idr' => 0, 'payroll_debt_idr' => 0, 'receivable_idr' => 0, 'historical_stock_grams' => 0];
        $table = null;
        $hash = null;
        $columns = [];
        $count = 0;
        $finish = function () use (&$tables, &$table, &$hash, &$count): void {
            if ($hash !== null) {
                $tables[$table] = ['count' => $count, 'sha256' => hash_final($hash)];
            }
        };
        foreach ($records as $record) {
            if (isset($record['table'])) {
                $finish();
                $table = $record['table'];
                $columns = $record['columns'];
                $hash = in_array($table, self::RUNTIME, true) ? null : hash_init('sha256');
                $count = 0;

                continue;
            }
            if (! isset($record['row'])) {
                continue;
            }
            $row = array_combine($columns, array_map(fn ($cell) => $cell === null ? null : base64_decode($cell, true), $record['row']));
            if ($hash !== null) {
                hash_update($hash, json_encode($record['row'], JSON_THROW_ON_ERROR)."\n");
                $count++;
            }
            switch ($table) {
                case 'cash_entries':
                    $metrics['cash_idr'] += ($row['direction'] === 'in' ? 1 : -1) * (int) $row['amount_idr'];
                    break;
                case 'weighings':
                    if ($row['status'] === 'posted') {
                        $posted['weighings'][$row['id']] = true;
                        $metrics['customer_debt_idr'] += (int) $row['total_purchase_idr'];
                    }
                    break;
                case 'customer_payments':
                case 'payroll_payments':
                    if ($row['status'] === 'posted') {
                        $posted[$table][$row['id']] = true;
                    }
                    break;
                case 'weighing_loaders':
                case 'customer_payment_allocations':
                case 'payroll_payment_allocations':
                    [$parent, $foreignKey, $amount] = match ($table) {
                        'weighing_loaders' => ['weighings', 'weighing_id', 'share_amount_idr'],
                        'customer_payment_allocations' => ['customer_payments', 'customer_payment_id', 'amount_idr'],
                        default => ['payroll_payments', 'payroll_payment_id', 'amount_idr'],
                    };
                    $allocations[$parent][$row[$foreignKey]] = ($allocations[$parent][$row[$foreignKey]] ?? 0) + (int) $row[$amount];
                    break;
                case 'factory_sales':
                case 'sort_sales':
                case 'sale_receipts':
                    if ($row['status'] === 'posted') {
                        $metrics['receivable_idr'] += $table === 'sale_receipts' ? -(int) $row['amount_idr'] : (int) $row['revenue_idr'];
                    }
                    break;
                case 'sort_stock_movements':
                    $metrics['historical_stock_grams'] += (int) $row['quantity_delta_grams'];
                    break;
                case 'attachments':
                    $proofs[] = ['storage_path' => $row['storage_path'], 'size_bytes' => (int) $row['size_bytes']];
                    break;
                case 'migrations':
                    $migrations[] = $row['migration'];
                    break;
            }
        }
        $finish();
        foreach (['weighings', 'customer_payments', 'payroll_payments'] as $parent) {
            $amount = array_sum(array_intersect_key($allocations[$parent] ?? [], $posted[$parent] ?? []));
            $metric = $parent === 'customer_payments' ? 'customer_debt_idr' : 'payroll_debt_idr';
            $metrics[$metric] += $parent === 'weighings' ? $amount : -$amount;
        }
        ksort($tables);
        sort($migrations);

        return ['tables' => $tables, 'metrics' => $metrics, 'proofs' => $proofs, 'migrations' => $migrations];
    }
}
