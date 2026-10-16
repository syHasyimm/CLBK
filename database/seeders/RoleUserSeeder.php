<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\AuditService;
use App\Support\Database\WriteTransaction;
use Illuminate\Database\Seeder;
use RuntimeException;

class RoleUserSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('RoleUserSeeder hanya dapat dijalankan pada lingkungan local atau testing.');
        }

        app(WriteTransaction::class)->run(function () {
            $audit = app(AuditService::class);

            foreach ([
                ['name' => 'Manajer Toke', 'email' => 'manager@clbk.test', 'role' => UserRole::Manager],
                ['name' => 'Supir CLBK', 'email' => 'supir@clbk.test', 'role' => UserRole::Driver],
                ['name' => 'Kasir CLBK', 'email' => 'kasir@clbk.test', 'role' => UserRole::Cashier],
            ] as $account) {
                $user = User::query()->firstOrCreate(
                    ['email' => $account['email']],
                    [
                        'name' => $account['name'],
                        'role' => $account['role'],
                        'password' => 'ClbkToke12345!',
                        'is_active' => true,
                    ],
                );

                if ($user->wasRecentlyCreated) {
                    $audit->record($user, 'seeded', $user, null, $audit->snapshot($user));
                }
            }
        });
    }
}
