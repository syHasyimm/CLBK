<?php

namespace App\Queries;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\BusinessClock;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class OperationalReport
{
    private ?string $timezone = null;

    public const TITLES = ['summary' => 'Ringkasan periode', 'weighings' => 'Timbang konsumen', 'factory' => 'Penjualan pabrik',
        'sort' => 'Penjualan sortir', 'receivables' => 'Piutang penjualan', 'debts' => 'Utang konsumen', 'payroll' => 'Upah muat', 'cash' => 'Mutasi kas & bank',
        'receipts' => 'Penerimaan penjualan', 'expenses' => 'Pengeluaran operasional', 'audit' => 'Audit transaksi'];

    public function views(User $actor): array
    {
        if ($actor->role === UserRole::Driver) {
            return [];
        }

        return array_filter(self::TITLES, fn ($key) => $actor->role === UserRole::Manager
            || $key !== 'audit', ARRAY_FILTER_USE_KEY);
    }

    public function filterKeys(string $view): array
    {
        return match ($view) {
            'weighings', 'debts' => ['vehicle_id', 'customer_id', 'status'],
            'factory' => ['vehicle_id', 'factory_id', 'status'],
            'receivables' => ['vehicle_id', 'factory_id', 'status'],
            'sort' => ['vehicle_id', 'status'],
            'payroll' => ['vehicle_id', 'worker_id', 'status'],
            'cash' => ['cash_account_id'],
            'receipts' => ['vehicle_id', 'factory_id', 'cash_account_id', 'status'],
            'expenses' => ['cash_account_id', 'expense_category_id', 'status'],
            'audit' => [],
            default => ['vehicle_id'],
        };
    }

    private function column(string $key, string $label, string $type = 'text', bool $sum = false): array
    {
        return compact('key', 'label', 'type', 'sum');
    }

    private function filters(Builder $query, array $filters, array $fields): void
    {
        foreach ($fields as $key => $column) {
            if (! empty($filters[$key])) {
                $query->where($column, $filters[$key]);
            }
        }
    }

    private function driver(Builder $query, User $actor, string $alias): void
    {
        if ($actor->role === UserRole::Driver) {
            $query->where(fn ($q) => $q->where(fn ($d) => $d->where($alias.'.workflow', 'direct')->where($alias.'.created_by', $actor->id))
                ->orWhere(fn ($l) => $l->where($alias.'.workflow', 'legacy')->whereIn($alias.'.trip_id', DB::table('trips')->select('id')->where('driver_id', $actor->id))));
        }
    }

    private function events(Builder $base, array $filters, string $date, string $reversed, array $amounts): Builder
    {
        $posted = (clone $base)->whereBetween($date, [$filters['from'], $filters['to']])->selectRaw("{$date} AS date, 'posting' AS event");
        $negative = (clone $base)->whereBetween(DB::raw($reversed), [$filters['from'], $filters['to']])->selectRaw("{$reversed} AS date, 'reversal' AS event");
        foreach ($amounts as $key => $expression) {
            $posted->selectRaw("{$expression} AS {$key}");
            $negative->selectRaw("-({$expression}) AS {$key}");
        }

        return DB::query()->fromSub($posted->unionAll($negative), 'r')->orderByDesc('date')->orderByDesc('id')->orderBy('event');
    }

    public function definition(User $actor, array $filters): array
    {
        $mysql = DB::connection()->getDriverName() === 'mysql';
        $view = $filters['view'];
        $c = fn ($key, $label, $type = 'text', $sum = false) => $this->column($key, $label, $type, $sum);
        $common = [$c('date', 'Tanggal'), $c('number', 'Dokumen'), $c('party', 'Pihak terkait')];
        $note = 'Tanggal mengikuti transaksi dan pembalikan. Baris pembalikan mengurangi angka pada tanggal pembalikannya.';
        if ($view === 'weighings' || $view === 'debts') {
            $q = DB::table('weighings as w')->join('customers as c', 'c.id', '=', 'w.customer_id')->join('vehicles as v', 'v.id', '=', 'w.vehicle_id')
                ->whereIn('w.status', $view === 'debts' ? ['posted'] : ['posted', 'voided'])
                ->select('w.id', 'w.number', 'w.status', 'c.name as party', 'v.plate_number as vehicle')
                ->selectRaw("'weighings.show' AS source_route, w.id AS source_id");
            $this->driver($q, $actor, 'w');
            $this->filters($q, $filters, ['vehicle_id' => 'w.vehicle_id', 'customer_id' => 'w.customer_id']);
            $paid = "(SELECT COALESCE(SUM(a.amount_idr),0) FROM customer_payment_allocations a JOIN customer_payments p ON p.id=a.customer_payment_id WHERE a.weighing_id=w.id AND p.status='posted')";
            if ($view === 'debts') {
                $q->whereBetween('w.business_date', [$filters['from'], $filters['to']])->selectRaw("w.business_date AS date, w.total_purchase_idr AS entitlement_idr, {$paid} AS paid_idr, w.total_purchase_idr-{$paid} AS remaining_idr")
                    ->selectRaw(($mysql ? 'DATEDIFF(?,w.business_date)' : 'CAST(julianday(?)-julianday(w.business_date) AS INTEGER)').' AS age_days', [app(BusinessClock::class)->date(app(BusinessClock::class)->now())]);
                $query = DB::query()->fromSub($q, 'r');
                $this->paymentStatus($query, $filters);
                $query->orderBy('date')->orderBy('id');
                $columns = [...$common, $c('vehicle', 'Mobil'), $c('entitlement_idr', 'Hak konsumen (Rp)', 'money', true), $c('paid_idr', 'Dibayar (Rp)', 'money', true), $c('remaining_idr', 'Sisa saat ini (Rp)', 'money', true), $c('age_days', 'Umur hak (hari)', 'number')];
                $note = 'Tanggal menyaring tanggal timbang sumber hak. Dibayar dan sisa memakai seluruh pembayaran sah sampai saat laporan dibuka. Umur dihitung dari tanggal timbang sampai hari ini.';
            } else {
                if (! empty($filters['status'])) {
                    $q->where('w.status', $filters['status']);
                }
                $workers = $mysql ? "GROUP_CONCAT(lw.name SEPARATOR ', ')" : "group_concat(lw.name, ', ')";
                $q->selectRaw("(SELECT {$workers} FROM weighing_loaders wl JOIN loading_workers lw ON lw.id=wl.worker_id WHERE wl.weighing_id=w.id) AS workers, w.loading_rate_snapshot_idr AS rate_idr");
                $q->selectRaw("(SELECT buy_price_snapshot_idr FROM weighing_items WHERE weighing_id=w.id AND fruit_type='TBS') AS tbs_price_idr, (SELECT buy_price_snapshot_idr FROM weighing_items WHERE weighing_id=w.id AND fruit_type='SORT') AS sort_price_idr");
                $offset = CarbonImmutable::parse($filters['from'], app(BusinessClock::class)->timezone())->offset;
                $reversalDate = $mysql ? "DATE(DATE_ADD(w.voided_at, INTERVAL {$offset} SECOND))" : "date(w.voided_at, '{$offset} seconds')";
                $query = $this->events($q, $filters, 'w.business_date', $reversalDate, [
                    'tbs_grams' => "(SELECT COALESCE(SUM(weight_grams),0) FROM weighing_items WHERE weighing_id=w.id AND fruit_type='TBS')",
                    'sort_grams' => "(SELECT COALESCE(SUM(weight_grams),0) FROM weighing_items WHERE weighing_id=w.id AND fruit_type='SORT')",
                    'purchase_idr' => 'w.total_purchase_idr', 'loading_idr' => 'w.total_loading_wage_idr']);
                $columns = [...$common, $c('vehicle', 'Mobil'), $c('event', 'Pencatatan'), $c('status', 'Status tiket'), $c('tbs_grams', 'TBS (kg)', 'weight', true), $c('sort_grams', 'Sortir (kg)', 'weight', true), $c('tbs_price_idr', 'Harga TBS terkunci (Rp/kg)', 'money'), $c('sort_price_idr', 'Harga sortir terkunci (Rp/kg)', 'money'), $c('purchase_idr', 'Hak konsumen (Rp)', 'money', true), $c('loading_idr', 'Hak upah (Rp)', 'money', true), $c('rate_idr', 'Tarif upah (Rp/kg)', 'money'), $c('workers', 'Anggota muat')];
            }
        } elseif (in_array($view, ['factory', 'sort'], true)) {
            $factory = $view === 'factory';
            $q = DB::table($view.'_sales as s')->join('vehicles as v', 'v.id', '=', 's.vehicle_id')->whereIn('s.status', ['posted', 'reversed'])
                ->select('s.id', 's.number', 's.status', 'v.plate_number as vehicle', 's.sell_price_snapshot_idr as price_idr')
                ->selectRaw("'{$view}-sales.show' AS source_route, s.id AS source_id");
            if ($factory) {
                $q->join('factories as f', 'f.id', '=', 's.factory_id')->addSelect('f.name as party', 's.invoice_number');
            } else {
                $q->selectRaw("COALESCE(NULLIF(s.buyer_name,''),'Pembeli sortir') AS party");
            }
            $this->driver($q, $actor, 's');
            $this->filters($q, $filters, ['vehicle_id' => 's.vehicle_id', ...($factory ? ['factory_id' => 's.factory_id'] : []), 'status' => 's.status']);
            $paid = "(SELECT COALESCE(SUM(amount_idr),0) FROM sale_receipts WHERE {$view}_sale_id=s.id AND status='posted')";
            if ($actor->role !== UserRole::Driver) {
                $q->selectRaw("{$paid} AS received_idr, CASE WHEN s.status='posted' THEN s.revenue_idr-{$paid} ELSE 0 END AS remaining_idr");
            }
            $q->selectRaw("(SELECT COUNT(*) FROM attachments WHERE entity_type='{$view}' AND entity_id=s.id) AS proof_count");
            $query = $this->events($q, $filters, 's.business_date', 's.reversal_business_date', [
                'sold_grams' => $factory ? 's.accepted_grams' : 's.manual_sold_grams', 'deduction_idr' => 's.deduction_idr', 'revenue_idr' => 's.revenue_idr']);
            $columns = [...$common, ...($factory ? [$c('invoice_number', 'Nomor nota pabrik')] : []), $c('vehicle', 'Mobil'), $c('event', 'Pencatatan'), $c('status', 'Status nota'), $c('sold_grams', 'Berat terjual (kg)', 'weight', true), $c('price_idr', 'Harga (Rp/kg)', 'money'), $c('deduction_idr', 'Potongan (Rp)', 'money', true), $c('revenue_idr', 'Pendapatan neto (Rp)', 'money', true),
                ...($actor->role !== UserRole::Driver ? [$c('received_idr', 'Diterima saat ini (Rp)', 'money'), $c('remaining_idr', 'Piutang saat ini (Rp)', 'money')] : []), $c('proof_count', 'Bukti', 'number')];
            $note .= ' Harga memakai snapshot nota. Kolom diterima/piutang menunjukkan keadaan nota saat ini, bukan arus kas periode dan tidak dijumlah ulang untuk baris pembalikan.';
        } elseif ($view === 'receivables') {
            $sources = [];
            foreach (['factory', 'sort'] as $kind) {
                $q = DB::table($kind.'_sales as s')->join('vehicles as v', 'v.id', '=', 's.vehicle_id')->where('s.status', 'posted')
                    ->whereBetween('s.business_date', [$filters['from'], $filters['to']])
                    ->select('s.id', 's.number', 'v.plate_number as vehicle')->selectRaw("'{$kind}-sales.show' AS source_route, s.id AS source_id, s.business_date AS date, '{$kind}' AS sale_type, s.revenue_idr AS entitlement_idr");
                if ($kind === 'factory') {
                    $q->join('factories as f', 'f.id', '=', 's.factory_id')->addSelect('f.name as party');
                    $this->filters($q, $filters, ['factory_id' => 's.factory_id']);
                } else {
                    $q->selectRaw("COALESCE(NULLIF(s.buyer_name,''),'Pembeli sortir') AS party");
                    if (! empty($filters['factory_id'])) {
                        $q->whereRaw('1=0');
                    }
                }
                $this->filters($q, $filters, ['vehicle_id' => 's.vehicle_id']);
                $paid = "(SELECT COALESCE(SUM(amount_idr),0) FROM sale_receipts WHERE {$kind}_sale_id=s.id AND status='posted')";
                $q->selectRaw("{$paid} AS paid_idr, s.revenue_idr-{$paid} AS remaining_idr");
                $sources[] = $q;
            }
            $query = DB::query()->fromSub($sources[0]->unionAll($sources[1]), 'r');
            $this->paymentStatus($query, $filters);
            $query->orderBy('date')->orderBy('sale_type')->orderBy('id');
            $columns = [...$common, $c('vehicle', 'Mobil'), $c('sale_type', 'Jenis penjualan'), $c('entitlement_idr', 'Nilai nota (Rp)', 'money', true), $c('paid_idr', 'Diterima (Rp)', 'money', true), $c('remaining_idr', 'Piutang saat ini (Rp)', 'money', true)];
            $note = 'Tanggal menyaring tanggal penjualan. Piutang berasal dari nota pabrik dan sortir sah dikurangi seluruh penerimaan sah saat ini, termasuk penerimaan lama.';
        } elseif ($view === 'payroll') {
            $q = app(PaymentBalances::class)->payroll()->toBase()->join('loading_workers as lw', 'lw.id', '=', 'weighing_loaders.worker_id')->join('vehicles as v', 'v.id', '=', 'w.vehicle_id')
                ->addSelect('lw.name as party', 'v.plate_number as vehicle')->selectRaw("'weighings.show' AS source_route, w.id AS source_id")
                ->whereBetween('w.business_date', [$filters['from'], $filters['to']]);
            $this->filters($q, $filters, ['vehicle_id' => 'w.vehicle_id', 'worker_id' => 'weighing_loaders.worker_id']);
            $query = DB::query()->fromSub($q, 'p')->select('id', 'number', 'party', 'vehicle', 'source_route', 'source_id', 'paid_idr')
                ->selectRaw('business_date AS date, share_amount_idr AS entitlement_idr, share_amount_idr-paid_idr AS remaining_idr, loading_basis_grams AS basis_grams, loading_rate_snapshot_idr AS rate_idr, member_count_snapshot AS member_count');
            $query = DB::query()->fromSub($query, 'r');
            $this->paymentStatus($query, $filters);
            $query->orderBy('date')->orderBy('id');
            $columns = [...$common, $c('vehicle', 'Mobil'), $c('basis_grams', 'Dasar berat tiket (kg)', 'weight'), $c('rate_idr', 'Tarif terkunci (Rp/kg)', 'money'), $c('member_count', 'Pembagi', 'number'), $c('entitlement_idr', 'Hak anggota (Rp)', 'money', true), $c('paid_idr', 'Dibayar (Rp)', 'money', true), $c('remaining_idr', 'Sisa saat ini (Rp)', 'money', true)];
            $note = 'Tanggal menyaring tanggal pekerjaan/timbang. Hak anggota dihitung per tiket memakai tarif dan pembagi terkunci. Dibayar dan sisa memakai seluruh pembayaran sah saat ini.';
        } elseif ($view === 'cash') {
            $ledger = DB::table('cash_entries as e')->join('cash_accounts as a', 'a.id', '=', 'e.cash_account_id')->leftJoin('cash_entries as original', 'original.id', '=', 'e.reversal_of_id')
                ->select('e.*', 'a.name as party')->selectRaw("SUM(CASE e.direction WHEN 'in' THEN e.amount_idr ELSE -e.amount_idr END) OVER (PARTITION BY e.cash_account_id ORDER BY e.occurred_at,e.id ROWS UNBOUNDED PRECEDING) AS balance_idr")
                ->selectRaw('COALESCE(e.customer_payment_id,original.customer_payment_id) AS customer_source, COALESCE(e.payroll_payment_id,original.payroll_payment_id) AS payroll_source, COALESCE(e.sale_receipt_id,original.sale_receipt_id) AS receipt_source, COALESCE(e.daily_expense_id,original.daily_expense_id) AS expense_source');
            $query = DB::query()->fromSub($ledger, 'r')->whereBetween('business_date', [$filters['from'], $filters['to']]);
            $this->filters($query, $filters, ['cash_account_id' => 'cash_account_id']);
            $query->select('r.*')->selectRaw("business_date AS date, CASE direction WHEN 'in' THEN amount_idr ELSE 0 END AS incoming_idr, CASE direction WHEN 'out' THEN amount_idr ELSE 0 END AS outgoing_idr, CASE direction WHEN 'in' THEN amount_idr ELSE -amount_idr END AS net_idr")
                ->orderBy('occurred_at')->orderBy('id');
            $columns = [...$common, $c('kind', 'Jenis mutasi'), $c('incoming_idr', 'Masuk (Rp)', 'money', true), $c('outgoing_idr', 'Keluar (Rp)', 'money', true), $c('net_idr', 'Perubahan saldo (Rp)', 'money', true), $c('balance_idr', 'Saldo akun setelah mutasi (Rp)', 'money'), $c('reference', 'Referensi')];
            $note = 'Tanggal mengikuti mutasi uang. Saldo berjalan memasukkan seluruh mutasi sebelumnya pada akun yang sama, termasuk mutasi di luar periode. Modal dan penarikan pemilik hanya memengaruhi kas.';
        } elseif ($view === 'expenses' || $view === 'receipts') {
            $receipt = $view === 'receipts';
            $kind = $receipt ? 'receipt' : 'expense';
            $table = $receipt ? 'sale_receipts' : 'daily_expenses';
            $q = DB::table($table.' as d')->join('cash_accounts as a', 'a.id', '=', 'd.cash_account_id')
                ->select('d.id', 'd.number', 'd.status', 'd.method', 'd.reference', 'a.name as account')
                ->selectRaw("'finance.show' AS source_route, d.id AS source_id, '{$kind}' AS finance_kind, (SELECT COUNT(*) FROM attachments WHERE entity_type='{$kind}' AND entity_id=d.id) AS proof_count");
            $fields = ['cash_account_id' => 'd.cash_account_id', 'status' => 'd.status'];
            if ($receipt) {
                $q->leftJoin('factory_sales as f', 'f.id', '=', 'd.factory_sale_id')->leftJoin('sort_sales as s', 's.id', '=', 'd.sort_sale_id')->leftJoin('factories as p', 'p.id', '=', 'f.factory_id')
                    ->selectRaw("COALESCE(p.name,NULLIF(s.buyer_name,''),'Pembeli sortir') AS party, COALESCE(f.number,s.number) AS sale_number, CASE WHEN d.factory_sale_id IS NULL THEN 'Sortir' ELSE 'Pabrik' END AS sale_type");
                $fields += ['vehicle_id' => DB::raw('COALESCE(f.vehicle_id,s.vehicle_id)'), 'factory_id' => 'f.factory_id'];
            } else {
                $q->join('expense_categories as c', 'c.id', '=', 'd.expense_category_id')->addSelect('c.name as party', 'd.description');
                $fields['expense_category_id'] = 'd.expense_category_id';
            }
            $this->filters($q, $filters, $fields);
            $query = $this->events($q, $filters, 'd.business_date', 'd.reversal_business_date', ['amount_idr' => 'd.amount_idr']);
            $columns = [...$common, $c('account', 'Akun'), ...($receipt ? [$c('sale_type', 'Jenis penjualan'), $c('sale_number', 'Nota asal')] : [$c('description', 'Keterangan')]), $c('event', 'Pencatatan'), $c('status', 'Status voucher'), $c('amount_idr', $receipt ? 'Penerimaan neto (Rp)' : 'Biaya neto (Rp)', 'money', true), $c('method', 'Metode'), $c('reference', 'Referensi'), $c('proof_count', 'Bukti', 'number')];
        } else {
            $clock = app(BusinessClock::class);
            $reasons = array_map(function ($path) use ($mysql) {
                $json = "JSON_EXTRACT(a.after_json,'$.{$path}')";

                return $mysql ? "CASE WHEN JSON_TYPE({$json})='NULL' THEN NULL ELSE JSON_UNQUOTE({$json}) END" : $json;
            }, ['reversal_reason', 'void_reason', 'correction_reason']);
            $reason = 'COALESCE(a.reason,'.implode(',', $reasons).') AS reason';
            $query = DB::table('audit_logs as a')->leftJoin('users as u', 'u.id', '=', 'a.actor_id')
                ->whereBetween('a.occurred_at', [CarbonImmutable::parse($filters['from'], $clock->timezone())->startOfDay()->utc(), CarbonImmutable::parse($filters['to'], $clock->timezone())->endOfDay()->utc()])
                ->select('a.id', 'a.occurred_at', 'u.name as party', 'a.action', 'a.entity_type', 'a.entity_id', 'a.before_json', 'a.after_json')
                ->selectRaw($reason)
                ->orderByDesc('a.occurred_at')->orderByDesc('a.id');
            $columns = [$c('occurred_at', 'Waktu (WIB)'), $c('party', 'Petugas'), $c('action', 'Aksi'), $c('entity_type', 'Jenis dokumen'), $c('entity_id', 'ID dokumen'), $c('changes', 'Perubahan'), $c('reason', 'Alasan koreksi')];
            $note = 'Riwayat tindakan yang tersimpan pada audit. Akses hanya untuk manajer; kredensial dan lokasi penyimpanan privat tidak disertakan dalam laporan.';
        }

        return ['title' => self::TITLES[$view], 'note' => $note, 'columns' => $columns, 'query' => $query];
    }

    private function paymentStatus(Builder $query, array $filters): void
    {
        if (! empty($filters['status'])) {
            $query->where('remaining_idr', $filters['status'] === 'paid' ? '=' : '>', 0);
            if ($filters['status'] === 'partial') {
                $query->where('paid_idr', '>', 0);
            }
        }
    }

    public function totals(array $definition): array
    {
        $columns = array_filter($definition['columns'], fn ($c) => $c['sum']);
        if ($columns === []) {
            return [];
        }
        $query = DB::query()->fromSub((clone $definition['query'])->reorder(), 'totals');
        foreach ($columns as $column) {
            $key = $column['key'];
            $query->selectRaw("COALESCE(SUM({$key}),0) AS {$key}");
        }

        return array_map('intval', (array) $query->first());
    }

    public function row(object $record, array $definition): array
    {
        $data = (array) $record;
        if (array_key_exists('before_json', $data)) {
            $data['changes'] = app(AuditChanges::class)->describe($data['before_json'], $data['after_json']);
        }
        $row = ['id' => $data['id'].':'.($data['source_route'] ?? '').':'.($data['event'] ?? '')];
        foreach ($definition['columns'] as $column) {
            $value = $data[$column['key']] ?? null;
            $row[$column['key']] = in_array($column['type'], ['weight', 'money', 'number'], true) ? ($value === null ? null : (int) $value) : $value;
        }
        $source = isset($data['source_route']) ? ['route' => $data['source_route'], 'params' => $data['source_id']] : null;
        if (($data['source_route'] ?? '') === 'finance.show') {
            $source['params'] = ['kind' => $data['finance_kind'], 'document' => $data['source_id']];
        }
        if (isset($data['balance_idr'])) {
            $source = ['route' => 'cash.index', 'params' => ['cash_account_id' => $data['cash_account_id']]];
            foreach (['customer', 'payroll', 'receipt', 'expense'] as $kind) {
                if ($data[$kind.'_source']) {
                    $source = ['route' => 'finance.show', 'params' => ['kind' => $kind, 'document' => $data[$kind.'_source']]];
                    break;
                }
            }
            $row['number'] = $data['number'] ?: 'Mutasi #'.$data['id'];
        }
        if (isset($data['occurred_at'])) {
            $this->timezone ??= app(BusinessClock::class)->timezone();
            $row['occurred_at'] = CarbonImmutable::parse($data['occurred_at'], 'UTC')->setTimezone($this->timezone)->format('Y-m-d H:i:s');
        }
        $row['source'] = $source;

        return $row;
    }
}
