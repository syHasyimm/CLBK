<?php

namespace App\Support;

use App\Models\AppSetting;
use Illuminate\Support\Facades\DB;

class DocumentNumber
{
    // Dipanggil hanya di dalam WriteTransaction; nomor terhapus tidak dipakai ulang.
    public function next(string $kind, string $date): string
    {
        $name = $kind.':'.$date;
        DB::table('document_sequences')->insertOrIgnore(['name' => $name, 'next_value' => 1]);
        $value = (int) DB::table('document_sequences')->where('name', $name)->value('next_value');
        DB::table('document_sequences')->where('name', $name)->update(['next_value' => $value + 1]);

        return AppSetting::values()['receipt_prefix'].'-'.$kind.'-'.str_replace('-', '', $date).'-'.str_pad((string) $value, 6, '0', STR_PAD_LEFT);
    }
}
