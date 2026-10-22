<?php

namespace App\Actions\Finance;

use App\Actions\Sales\SaveSale;
use App\Enums\FinanceKind;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\ExpenseCategory;
use App\Models\FinanceDocument;
use App\Models\LoadingWorker;
use App\Models\User;
use App\Models\Weighing;
use App\Queries\PaymentBalances;
use App\Services\AuditService;
use App\Services\BusinessCash;
use App\Services\CashLedger;
use App\Services\IdempotencyService;
use App\Services\WhatsApp\NotificationOutbox;
use App\Support\BusinessClock;
use App\Support\Database\WriteTransaction;
use App\Support\DocumentNumber;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class PostFinance
{
    public function handle(User $actor, FinanceKind $kind, array $data): FinanceDocument
    {
        return app(WriteTransaction::class)->run(function () use ($actor, $kind, $data) {
            $actor = $actor->fresh();
            Gate::forUser($actor)->authorize('create', $kind->model());
            $data['cash_account_id'] ??= app(BusinessCash::class)->account()?->id;
            if (isset($data['selected_ids'])) {
                $data['selected_ids'] = array_map('intval', $data['selected_ids']);
                sort($data['selected_ids']);
            }
            $payload = $data;
            unset($payload['idempotency_key']);
            $id = app(IdempotencyService::class)->run($actor, 'post-finance-'.$kind->value, $data['idempotency_key'], $payload, function () use ($actor, $kind, $data) {
                $clock = app(BusinessClock::class);
                $time = $clock->parse($data['occurred_at'], 'occurred_at');
                if ($time->gt($clock->now())) {
                    throw ValidationException::withMessages(['occurred_at' => 'Tanggal transaksi tidak boleh di masa depan.']);
                }
                if ($clock->date($time) < $clock->date($clock->now()) && trim($data['backdate_reason'] ?? '') === '') {
                    throw ValidationException::withMessages(['backdate_reason' => 'Isi alasan pencatatan transaksi tanggal sebelumnya.']);
                }
                $amount = (int) $data['amount_idr'];
                $account = app(BusinessCash::class)->forPosting(isset($data['cash_account_id']) ? (int) $data['cash_account_id'] : null);
                $reference = trim($data['reference'] ?? '');
                $reference = $reference === '' ? null : $reference;
                if ($data['method'] === 'bank_transfer' && $reference === null) {
                    throw ValidationException::withMessages(['reference' => 'Isi nomor atau referensi transfer bank.']);
                }
                $attributes = ['cash_account_id' => $account->id, 'occurred_at' => $time, 'business_date' => $clock->date($time),
                    'amount_idr' => $amount, 'method' => $data['method'], 'reference' => $reference,
                    'notes' => $data['notes'] ?? null, 'backdate_reason' => $data['backdate_reason'] ?? null, 'status' => 'posted',
                    'posted_at' => $clock->now(), 'version' => 1, 'created_by' => $actor->id];
                $allocations = [];
                $balances = app(PaymentBalances::class);
                if (in_array($kind, [FinanceKind::Customer, FinanceKind::Payroll], true)) {
                    $customer = $kind === FinanceKind::Customer;
                    $automatic = $customer || ! empty($data['all_in_period']);
                    ($customer ? Customer::class : LoadingWorker::class)::findOrFail($data['recipient_id']);
                    if ($customer && isset($data['selected_ids']) && Weighing::whereIn('id', $data['selected_ids'])
                        ->where('customer_id', $data['recipient_id'])->where('status', 'posted')->count() !== count($data['selected_ids'])) {
                        throw ValidationException::withMessages(['selected_ids' => 'Referensi tiket harus sah dan milik konsumen yang sama.']);
                    }
                    $query = $customer ? $balances->customers(true)->where('customer_id', $data['recipient_id']) : $balances->payroll()->where('worker_id', $data['recipient_id'])->whereBetween('w.business_date', [$data['period_start'], $data['period_end']]);
                    if (! $automatic) {
                        $query->whereIn($customer ? 'weighings.id' : 'weighing_loaders.id', $data['selected_ids']);
                    }
                    $rows = $query->orderBy($customer ? 'weighings.weighed_at' : 'w.weighed_at')->orderBy($customer ? 'weighings.id' : 'weighing_loaders.id')->get();
                    if ($automatic) {
                        $rows = $rows->filter(fn ($row) => ($customer ? $row->total_purchase_idr : $row->share_amount_idr) > (int) $row->paid_idr);
                    }
                    if ((! $automatic && $rows->count() !== count($data['selected_ids'])) || $rows->contains(fn ($r) => $time->lt($customer ? $r->weighed_at : CarbonImmutable::parse($r->weighed_at, 'UTC')))) {
                        throw ValidationException::withMessages($automatic && ! ($customer && isset($data['selected_ids']))
                            ? ['operation' => 'Terdapat timbangan setelah waktu pembayaran. Periksa kembali tanggal dan waktu pembayaran.']
                            : ['selected_ids' => 'Pilih hak sah milik penerima yang sama, dalam periode dan sebelum waktu pembayaran.']);
                    }
                    $total = $rows->sum(fn ($r) => ($customer ? $r->total_purchase_idr : $r->share_amount_idr) - (int) $r->paid_idr);
                    if ($total !== (int) $data['expected_remaining_idr']) {
                        throw ValidationException::withMessages(['operation' => 'Sisa hak telah berubah. Muat ulang dan periksa ringkasan sebelum membayar.']);
                    }
                    if ($customer && $amount !== $total) {
                        throw ValidationException::withMessages(['amount_idr' => 'Pembayaran konsumen wajib melunasi seluruh tiket yang belum lunas. Nominal tidak boleh dicicil atau dibatasi oleh pilihan tiket.']);
                    }
                    if ($amount <= 0 || $amount > $total) {
                        throw ValidationException::withMessages(['amount_idr' => $automatic ? 'Nominal melebihi sisa upah dalam periode ini.' : 'Nominal melebihi sisa hak yang dipilih.']);
                    }
                    $left = $amount;
                    foreach ($rows as $row) {
                        $take = min($left, ($customer ? $row->total_purchase_idr : $row->share_amount_idr) - (int) $row->paid_idr);
                        if ($take > 0) {
                            $allocations[] = [($customer ? 'weighing_id' : 'weighing_loader_id') => $row->id, 'amount_idr' => $take];
                            $left -= $take;
                        }
                    }
                    $attributes[$customer ? 'customer_id' : 'worker_id'] = (int) $data['recipient_id'];
                    if (! $customer) {
                        $attributes += ['period_start' => $data['period_start'], 'period_end' => $data['period_end']];
                    }
                } elseif ($kind === FinanceKind::Receipt) {
                    if (! in_array($data['sale_kind'], ['factory', 'sort'], true)) {
                        throw ValidationException::withMessages(['sale_kind' => 'Pilih penjualan pabrik atau sortir.']);
                    }
                    $sale = SaveSale::model($data['sale_kind'])::findOrFail($data['sale_id']);
                    $remaining = $sale->revenue_idr - $balances->salePaid($sale);
                    if ($sale->status !== 'posted' || $time->lt($sale->sold_at) || $remaining !== (int) $data['expected_remaining_idr'] || $amount <= 0 || $amount > $remaining) {
                        throw ValidationException::withMessages(['operation' => 'Nota atau sisa piutang berubah. Periksa nota sah dan waktu penerimaan.']);
                    }
                    $attributes[$sale->kind().'_sale_id'] = $sale->id;
                } else {
                    if (! ExpenseCategory::findOrFail($data['expense_category_id'])->is_active) {
                        throw ValidationException::withMessages(['expense_category_id' => 'Kategori pengeluaran harus aktif.']);
                    }
                    if (! empty($data['trip_id'])) {
                        throw ValidationException::withMessages(['trip_id' => 'Pengeluaran harian tidak lagi memakai perjalanan muatan.']);
                    }
                    $attributes += ['expense_category_id' => (int) $data['expense_category_id'], 'trip_id' => null, 'description' => trim($data['description'])];
                }
                $direction = $kind === FinanceKind::Receipt ? 'in' : 'out';
                app(CashLedger::class)->check($account->id, $direction, $amount, $time);
                $attributes['number'] = app(DocumentNumber::class)->next($kind->numberPrefix(), $attributes['business_date']);
                $document = $kind->model()::create($attributes);
                foreach ($allocations as $allocation) {
                    DB::table($kind->value.'_payment_allocations')->insert($allocation + [$kind->sourceColumn() => $document->id]);
                }
                DB::table('cash_entries')->insert(['cash_account_id' => $account->id, 'direction' => $direction, 'amount_idr' => $amount,
                    'occurred_at' => $time, 'business_date' => $attributes['business_date'], 'kind' => $kind->value, $kind->sourceColumn() => $document->id,
                    'reference' => $attributes['reference'], 'backdate_reason' => $attributes['backdate_reason'], 'created_by' => $actor->id]);
                if ($kind === FinanceKind::Expense) {
                    DB::table('profit_entries')->insert(['category' => 'operating_expense', 'amount_delta_idr' => $amount, 'occurred_at' => $time,
                        'business_date' => $attributes['business_date'], 'trip_id' => $document->trip_id, 'daily_expense_id' => $document->id, 'created_by' => $actor->id]);
                }
                app(AuditService::class)->record($actor, 'post-finance', $document, null, app(AuditService::class)->snapshot($document) + ['allocations' => $allocations]);

                if ($document instanceof CustomerPayment) {
                    app(NotificationOutbox::class)->payment($document);
                }

                return $document->id;
            });

            return $kind->model()::findOrFail($id);
        });
    }
}
