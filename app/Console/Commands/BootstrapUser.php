<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\AuditService;
use App\Support\Database\WriteTransaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class BootstrapUser extends Command
{
    protected $signature = 'clbk:bootstrap-user';

    protected $description = 'Membuat satu akun awal ketika belum ada pengguna';

    public function handle(WriteTransaction $transactions, AuditService $audit): int
    {
        if (User::query()->exists()) {
            $this->error('Akun awal sudah tersedia. Kelola akun tambahan melalui menu Akun Pengguna.');

            return self::FAILURE;
        }

        $data = [
            'name' => $this->ask('Nama pengguna'),
            'email' => $this->ask('Email'),
            'password' => $this->secret('Kata sandi (minimal 12 karakter, huruf dan angka)'),
            'password_confirmation' => $this->secret('Ulangi kata sandi'),
        ];

        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $created = $transactions->run(function () use ($data, $audit) {
            if (User::query()->exists()) {
                return false;
            }

            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'role' => UserRole::Manager,
                'is_active' => true,
            ]);
            $audit->record($user, 'bootstrapped', $user, null, $audit->snapshot($user));

            return true;
        });

        if (! $created) {
            $this->error('Akun awal sudah dibuat oleh proses lain.');

            return self::FAILURE;
        }

        $this->info('Akun awal berhasil dibuat. Silakan masuk ke CLBK TOKE.');

        return self::SUCCESS;
    }
}
