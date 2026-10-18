<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\Testing\WeighingDateFilterData;
use Illuminate\Database\Seeder;
use RuntimeException;

class WeighingDateFilterSeeder extends Seeder
{
    public const FROM = WeighingDateFilterData::FROM;

    public const TO = WeighingDateFilterData::TO;

    public const CUSTOMER_CODE = WeighingDateFilterData::CUSTOMER_CODE;

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('WeighingDateFilterSeeder hanya untuk database local atau testing yang terpisah dari produksi.');
        }
        app(WeighingDateFilterData::class)->seed(
            User::where('email', 'manager@clbk.test')->firstOrFail(),
            User::where('email', 'supir@clbk.test')->firstOrFail(),
        );
    }
}
