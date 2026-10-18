<?php

namespace Database\Seeders;

use App\Models\AppSetting;
use App\Models\CashAccount;
use App\Models\ExpenseCategory;
use App\Services\BusinessCash;
use App\Support\Database\WriteTransaction;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BusinessDefaultsSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        app(WriteTransaction::class)->run(function () {
            foreach (AppSetting::defaults() as $key => $value) {
                AppSetting::query()->firstOrCreate(['key' => $key], ['value_json' => $value]);
            }
            if (app(BusinessCash::class)->account() === null) {
                CashAccount::query()->firstOrCreate(
                    ['code' => 'KAS-UTAMA'],
                    ['name' => 'Kas Utama', 'type' => 'cash', 'is_active' => true],
                );
            }
            foreach ([
                'BBM' => 'BBM', 'TOL' => 'Tol', 'PARKIR' => 'Parkir',
                'PERAWATAN' => 'Perawatan mobil', 'MAKAN' => 'Makan operasional',
                'LAINNYA' => 'Biaya operasional lain',
            ] as $code => $name) {
                ExpenseCategory::query()->firstOrCreate(['code' => $code], ['name' => $name, 'is_active' => true]);
            }
        });
    }
}
