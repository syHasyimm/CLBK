<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\CashAccount;
use App\Models\Customer;
use App\Models\ExpenseCategory;
use App\Models\Factory;
use App\Models\LoadingWorker;
use App\Models\Vehicle;
use App\Queries\CashFlowSummary;
use App\Queries\OperationalReport;
use App\Queries\PeriodSummary;
use App\Services\BusinessCash;
use App\Support\BusinessClock;
use App\Support\Database\ReadSnapshot;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ReportController extends Controller
{
    private function filters(Request $request): array
    {
        $reports = app(OperationalReport::class);
        $view = $request->validate(['view' => ['nullable', 'string', Rule::in(array_keys(OperationalReport::TITLES))]])['view'] ?? 'summary';
        abort_unless(array_key_exists($view, $reports->views($request->user())), 403);
        $statuses = match ($view) {
            'debts', 'payroll', 'receivables' => ['unpaid', 'partial', 'paid'],
            'weighings' => ['posted', 'voided'],
            'factory', 'sort', 'receipts', 'expenses' => ['posted', 'reversed'],
            default => [],
        };
        $rules = ['view' => ['nullable', Rule::in(array_keys(OperationalReport::TITLES))],
            'from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', ...($request->filled('from') ? ['after_or_equal:from'] : [])],
            'status' => ['nullable', $statuses === [] ? 'prohibited' : Rule::in($statuses)]];
        $tables = ['vehicle_id' => 'vehicles', 'customer_id' => 'customers', 'factory_id' => 'factories', 'worker_id' => 'loading_workers',
            'cash_account_id' => 'cash_accounts', 'expense_category_id' => 'expense_categories'];
        $keys = $reports->filterKeys($view);
        foreach ($tables as $key => $table) {
            $rules[$key] = in_array($key, $keys, true) ? ['nullable', 'integer', 'min:1', 'exists:'.$table.',id'] : ['prohibited'];
        }
        $data = $request->validate($rules);
        if (in_array('cash_account_id', $keys, true)) {
            $data['cash_account_id'] = app(BusinessCash::class)->account()?->id ?? -1;
        }
        $today = app(BusinessClock::class)->date(app(BusinessClock::class)->now());

        return ['view' => $view, 'from' => $data['from'] ?? $data['to'] ?? $today, 'to' => $data['to'] ?? $data['from'] ?? $today]
            + array_combine($keys, array_map(fn ($key) => isset($data[$key]) && $data[$key] !== '' ? $data[$key] : null, $keys))
            + ['vehicle_id' => null];
    }

    private function options(string $view): array
    {
        $models = ['vehicle_id' => [Vehicle::class, 'plate_number'], 'customer_id' => [Customer::class, 'name'],
            'factory_id' => [Factory::class, 'name'], 'worker_id' => [LoadingWorker::class, 'name'],
            'cash_account_id' => [CashAccount::class, 'name'], 'expense_category_id' => [ExpenseCategory::class, 'name']];
        $result = [];
        foreach (app(OperationalReport::class)->filterKeys($view) as $key) {
            if (isset($models[$key])) {
                [$model, $label] = $models[$key];
                $query = $model::query();
                if ($key === 'cash_account_id') {
                    $query->whereKey(app(BusinessCash::class)->account()?->id ?? 0);
                }
                $result[$key] = $query->orderBy($label)->get(['id', $label])->map(fn ($record) => ['id' => $record->id, 'label' => $record->$label]);
            }
        }

        return $result;
    }

    public function index(Request $request)
    {
        $filters = $this->filters($request);

        return app(ReadSnapshot::class)->run(function () use ($request, $filters) {
            $reports = app(OperationalReport::class);
            $report = null;
            if ($filters['view'] !== 'summary') {
                $definition = $reports->definition($request->user(), $filters);
                $report = array_diff_key($definition, ['query' => true]) + ['totals' => $reports->totals($definition),
                    'rows' => $definition['query']->paginate(50)->withQueryString()->through(fn ($row) => $reports->row($row, $definition))];
                if ($filters['view'] === 'cash') {
                    $report['cashflow'] = app(CashFlowSummary::class)->get($filters['from'], $filters['to'], $filters['cash_account_id'] ?? null);
                }
            }

            return Inertia::render('Reports/Index', ['filters' => $filters, 'views' => $reports->views($request->user()),
                'viewFilters' => array_map(fn ($view) => $reports->filterKeys($view), array_combine(array_keys($reports->views($request->user())), array_keys($reports->views($request->user())))),
                'options' => $this->options($filters['view']), 'report' => $report,
                'summary' => $filters['view'] === 'summary' ? app(PeriodSummary::class)->get($request->user(), $filters) : null,
                'vehicles' => Vehicle::orderBy('plate_number')->get(['id', 'plate_number'])]);
        });
    }

    private function summaryDefinition(Request $request, array $filters): array
    {
        $summary = app(PeriodSummary::class)->get($request->user(), $filters);
        $labels = ['weighing_count' => 'Transaksi timbang neto', 'tbs_bought_grams' => 'TBS dibeli (kg)', 'sort_bought_grams' => 'Sortir dibeli (kg)',
            'factory_sold_grams' => 'TBS dijual (kg)', 'sort_sold_grams' => 'Sortir dijual (kg)', 'purchase_idr' => 'Pembelian (Rp)',
            'revenue_idr' => 'Pendapatan (Rp)', 'loading_idr' => 'Hak upah (Rp)', 'expense_idr' => 'Pengeluaran (Rp)', 'period_difference_idr' => 'Keuntungan bersih periode (Rp)'];
        $columns = [['key' => 'date', 'label' => 'Tanggal', 'type' => 'text', 'sum' => false]];
        foreach ($summary['totals'] as $key => $value) {
            if ($key === 'expense_idr' && $filters['vehicle_id']) {
                continue;
            }
            $columns[] = ['key' => $key, 'label' => $labels[$key], 'type' => str_ends_with($key, '_grams') ? 'weight' : (str_ends_with($key, '_idr') ? 'money' : 'number'), 'sum' => true];
        }

        return ['title' => 'Ringkasan periode', 'note' => 'Tanggal mengikuti transaksi dan pembalikan. Keuntungan bersih periode = penjualan pabrik + sortir − pembelian buah − hak upah − pengeluaran operasional pada periode yang sama. Pembayaran konsumen dan gaji tidak dikurangkan lagi. Pembelian dan penjualan dapat terjadi pada tanggal yang berbeda.',
            'columns' => $columns, 'rows' => collect($summary['days']), 'totals' => $summary['totals'], 'summary' => $summary];
    }

    private function dataset(Request $request, array $filters): array
    {
        if ($filters['view'] === 'summary') {
            return $this->summaryDefinition($request, $filters);
        }
        $reports = app(OperationalReport::class);
        $definition = $reports->definition($request->user(), $filters);

        return array_diff_key($definition, ['query' => true]) + ['totals' => $reports->totals($definition),
            'rows' => $definition['query']->cursor()->map(fn ($row) => $reports->row($row, $definition)),
            ...($filters['view'] === 'cash' ? ['cashflow' => app(CashFlowSummary::class)->get($filters['from'], $filters['to'], $filters['cash_account_id'] ?? null)] : [])];
    }

    private function csvValue(mixed $value, string $type): string
    {
        if ($value === null) {
            return '';
        }
        if ($type === 'weight') {
            $grams = (int) $value;

            return ($grams < 0 ? '-' : '').intdiv(abs($grams), 1000).'.'.str_pad((string) (abs($grams) % 1000), 3, '0', STR_PAD_LEFT);
        }
        $text = (string) $value;

        // User-authored text must not become a spreadsheet formula. Numeric negatives stay numeric.
        return $type === 'text' && preg_match('/^[\s\x00-\x1F]*[=+@-]/u', $text) ? "'".$text : $text;
    }

    public function export(Request $request)
    {
        $filters = $this->filters($request);

        return response()->streamDownload(function () use ($request, $filters) {
            app(ReadSnapshot::class)->run(function () use ($request, $filters) {
                $report = $this->dataset($request, $filters);
                $stream = fopen('php://output', 'wb');
                fwrite($stream, "\xEF\xBB\xBF");
                $sources = $filters['view'] !== 'summary';
                fputcsv($stream, [...array_column($report['columns'], 'label'), ...($sources ? ['Tautan dokumen sumber'] : [])], ';', '"', '');
                foreach ($report['rows'] as $row) {
                    $values = array_map(fn ($column) => $this->csvValue($row[$column['key']] ?? null, $column['type']), $report['columns']);
                    if ($sources) {
                        $values[] = $row['source'] ? route($row['source']['route'], $row['source']['params']) : '';
                    }
                    fputcsv($stream, $values, ';', '"', '');
                }
                if ($report['totals'] !== []) {
                    $total = array_map(fn ($column) => $column['sum'] ? $this->csvValue($report['totals'][$column['key']], $column['type']) : '', $report['columns']);
                    $total[0] = 'TOTAL';
                    if ($sources) {
                        $total[] = '';
                    }
                    fputcsv($stream, $total, ';', '"', '');
                }
                fclose($stream);
            });
        }, 'CLBK-'.$filters['view'].'-'.$filters['from'].'-'.$filters['to'].'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function print(Request $request)
    {
        $filters = $this->filters($request);

        return app(ReadSnapshot::class)->run(function () use ($request, $filters) {
            $report = $this->dataset($request, $filters);
            $labels = [];
            foreach ($this->options($filters['view']) as $key => $options) {
                if (! empty($filters[$key])) {
                    $labels[] = $options->firstWhere('id', $filters[$key])['label'];
                }
            }
            $html = view('reports.print', ['report' => $report, 'filters' => $filters, 'selectedLabels' => $labels,
                'businessName' => AppSetting::values()['business_name'], 'preparedAt' => app(BusinessClock::class)->local(app(BusinessClock::class)->now()),
                'format' => fn ($value, $type) => $value === null ? '—' : ($type === 'money' ? 'Rp '.number_format($value, 0, ',', '.') : ($type === 'weight' ? number_format($value / 1000, 3, ',', '.').' kg' : (string) $value))])->render();

            return response($html)->header('Cache-Control', 'private, no-store');
        });
    }
}
