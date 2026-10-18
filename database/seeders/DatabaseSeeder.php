<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call(BusinessDefaultsSeeder::class);

        if (app()->environment(['local', 'testing'])) {
            $this->call([
                RoleUserSeeder::class,
                WeighingDateFilterSeeder::class,
            ]);
        }
    }
}
