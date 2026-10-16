<?php

namespace App\Services;

use PDO;
use RuntimeException;

final class BackupSnapshot
{
    private const RUNTIME = ['sessions', 'password_reset_tokens', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs'];

    public function inspect(string $file): array
    {
        $pdo = new PDO('sqlite:'.$file, options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('PRAGMA query_only=ON');
        if ($pdo->query('PRAGMA integrity_check')->fetchColumn() !== 'ok' || $pdo->query('PRAGMA foreign_key_check')->fetchAll() !== []) {
            throw new RuntimeException('Pemeriksaan integritas atau foreign key database gagal.');
        }
        $tables = [];
        foreach ($pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN) as $table) {
            if (in_array($table, self::RUNTIME, true)) {
                continue;
            }
            $quoted = '"'.str_replace('"', '""', $table).'"';
            $columns = $pdo->query('PRAGMA table_info('.$quoted.')')->fetchAll(PDO::FETCH_ASSOC);
            $order = implode(',', array_map(fn ($column) => '"'.str_replace('"', '""', $column['name']).'"', $columns));
            $hash = hash_init('sha256');
            $count = 0;
            foreach ($pdo->query('SELECT * FROM '.$quoted.' ORDER BY '.$order, PDO::FETCH_ASSOC) as $row) {
                hash_update($hash, json_encode($row, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE)."\n");
                $count++;
            }
            $tables[$table] = ['count' => $count, 'sha256' => hash_final($hash)];
        }
        $sum = fn ($sql): int => (int) $pdo->query($sql)->fetchColumn();
        $metrics = [
            'cash_idr' => $sum("SELECT COALESCE(SUM(CASE direction WHEN 'in' THEN amount_idr ELSE -amount_idr END),0) FROM cash_entries"),
            'customer_debt_idr' => $sum("SELECT COALESCE(SUM(total_purchase_idr),0) FROM weighings WHERE status='posted'")
                - $sum("SELECT COALESCE(SUM(a.amount_idr),0) FROM customer_payment_allocations a JOIN customer_payments p ON p.id=a.customer_payment_id WHERE p.status='posted'"),
            'payroll_debt_idr' => $sum("SELECT COALESCE(SUM(l.share_amount_idr),0) FROM weighing_loaders l JOIN weighings w ON w.id=l.weighing_id WHERE w.status='posted'")
                - $sum("SELECT COALESCE(SUM(a.amount_idr),0) FROM payroll_payment_allocations a JOIN payroll_payments p ON p.id=a.payroll_payment_id WHERE p.status='posted'"),
            'receivable_idr' => $sum("SELECT COALESCE(SUM(revenue_idr),0) FROM factory_sales WHERE status='posted'")
                + $sum("SELECT COALESCE(SUM(revenue_idr),0) FROM sort_sales WHERE status='posted'")
                - $sum("SELECT COALESCE(SUM(amount_idr),0) FROM sale_receipts WHERE status='posted'"),
            'historical_stock_grams' => $sum('SELECT COALESCE(SUM(quantity_delta_grams),0) FROM sort_stock_movements'),
        ];
        $proofs = $pdo->query('SELECT storage_path, size_bytes FROM attachments ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        $migrations = $pdo->query('SELECT migration FROM migrations ORDER BY migration')->fetchAll(PDO::FETCH_COLUMN);

        return ['tables' => $tables, 'metrics' => $metrics, 'proofs' => $proofs, 'migrations' => $migrations];
    }
}
