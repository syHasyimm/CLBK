<?php

namespace App\Queries;

class AuditChanges
{
    // Only known business fields enter a report; never export a complete audit payload.
    private const FIELDS = [
        'number' => 'Nomor', 'name' => 'Nama', 'role' => 'Peran', 'is_active' => 'Aktif', 'status' => 'Status',
        'plate_number' => 'Nomor mobil', 'business_name' => 'Nama usaha', 'business_date' => 'Tanggal usaha',
        'weighed_at' => 'Waktu timbang', 'sold_at' => 'Waktu jual', 'occurred_at' => 'Waktu kejadian',
        'effective_from' => 'Harga mulai berlaku', 'customer_id' => 'ID konsumen', 'vehicle_id' => 'ID mobil',
        'assigned_vehicle_id' => 'ID mobil penugasan supir',
        'factory_id' => 'ID pabrik', 'cash_account_id' => 'ID akun kas', 'expense_category_id' => 'ID kategori',
        'invoice_number' => 'Nota pabrik', 'buyer_name' => 'Pembeli', 'reference' => 'Referensi',
        'total_purchase_idr' => 'Hak konsumen (Rp)', 'total_loading_wage_idr' => 'Hak upah (Rp)',
        'loading_rate_snapshot_idr' => 'Tarif upah (Rp/kg)', 'sell_price_snapshot_idr' => 'Harga jual (Rp/kg)',
        'tbs_buy_price_idr' => 'Harga TBS (Rp/kg)', 'sort_buy_price_idr' => 'Harga sortir (Rp/kg)',
        'loading_rate_idr' => 'Tarif upah (Rp/kg)', 'revenue_idr' => 'Pendapatan (Rp)',
        'deduction_idr' => 'Potongan (Rp)', 'amount_idr' => 'Nominal (Rp)',
        'accepted_grams' => 'Bersih pabrik (gram)', 'manual_sold_grams' => 'Sortir terjual (gram)',
        'loading_basis_grams' => 'Dasar upah (gram)', 'reversal_reason' => 'Alasan pembalikan',
        'void_reason' => 'Alasan pembatalan', 'correction_reason' => 'Alasan koreksi', 'reason' => 'Alasan',
    ];

    public function describe(?string $beforeJson, ?string $afterJson): string
    {
        $before = json_decode($beforeJson ?? 'null', true) ?? [];
        $after = json_decode($afterJson ?? 'null', true) ?? [];
        $changes = [];
        foreach (self::FIELDS as $key => $label) {
            $old = $before[$key] ?? null;
            $new = $after[$key] ?? null;
            if ($old !== $new && (is_scalar($old) || $old === null) && (is_scalar($new) || $new === null)) {
                $display = fn ($value) => $value === null ? '—' : (is_bool($value) ? ($value ? 'Ya' : 'Tidak') : (string) $value);
                $changes[] = $label.': '.$display($old).' → '.$display($new);
            }
        }

        return implode('; ', $changes);
    }
}
