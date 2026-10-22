<?php

namespace App\Services\WhatsApp;

use App\Models\AppSetting;
use App\Models\CustomerPayment;
use App\Models\Weighing;
use App\Support\BusinessClock;
use Illuminate\Support\Facades\DB;

final class MessageTemplate
{
    public function weighing(Weighing $weighing, bool $voided = false): string
    {
        $weighing->loadMissing('customer', 'items');
        $lines = [$this->business(), $voided ? 'PEMBATALAN TIMBANGAN' : ($weighing->replaces_weighing_id ? 'KOREKSI HASIL TIMBANGAN' : 'HASIL TIMBANGAN'),
            'Konsumen: '.$this->text($weighing->customer->name), 'Nomor tiket: '.$weighing->number,
            'Tanggal: '.$weighing->weighed_at->timezone(app(BusinessClock::class)->timezone())->format('d-m-Y H:i').' WIB'];
        if ($weighing->replaces_weighing_id) {
            $lines[] = 'Menggantikan tiket: '.Weighing::findOrFail($weighing->replaces_weighing_id)->number;
        }
        if ($voided) {
            $lines[] = 'Tiket ini telah dibatalkan. Abaikan hasil timbangan sebelumnya.';
        } else {
            foreach (['TBS' => 'Berat TBS', 'SORT' => 'Berat sortir'] as $type => $label) {
                $grams = (int) $weighing->items->where('fruit_type', $type)->sum('weight_grams');
                $lines[] = $label.': '.number_format($grams / 1000, 3, ',', '.').' kg';
            }
        }

        // Deliberate allowlist: no prices, purchase amounts, wages or balances.
        return implode("\n", $lines);
    }

    public function payment(CustomerPayment $payment, bool $reversed = false): string
    {
        $payment->loadMissing('customer');
        $tickets = DB::table('customer_payment_allocations')->where('customer_payment_id', $payment->id)->count();

        return implode("\n", [$this->business(), $reversed ? 'PEMBATALAN PEMBAYARAN' : 'KONFIRMASI PEMBAYARAN',
            'Konsumen: '.$this->text($payment->customer->name), 'Nomor bukti: '.$payment->number,
            'Tanggal: '.$payment->occurred_at->timezone(app(BusinessClock::class)->timezone())->format('d-m-Y H:i').' WIB',
            'Nominal: Rp '.number_format($payment->amount_idr, 0, ',', '.'),
            'Metode: '.($payment->method === 'cash' ? 'Tunai' : 'Transfer'),
            'Jumlah tiket: '.$tickets,
            $reversed ? 'Pembayaran ini telah dibalik. Konfirmasi pembayaran sebelumnya tidak berlaku.' : 'Pembayaran telah dicatat. Terima kasih.']);
    }

    public function test(): string
    {
        return $this->business()."\nPESAN TES\nNotifikasi WhatsApp CLBK TOKE terhubung melalui Fonnte.";
    }

    private function business(): string
    {
        return $this->text(AppSetting::values()['business_name']);
    }

    private function text(string $value): string
    {
        return preg_replace('/[\r\n\t]+/', ' ', $value);
    }
}
