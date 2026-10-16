<?php

namespace App\Enums;

use App\Models\CashAccount;
use App\Models\Customer;
use App\Models\ExpenseCategory;
use App\Models\Factory;
use App\Models\LoadingWorker;
use App\Models\Vehicle;

enum MasterResource: string
{
    case Customers = 'customers';
    case LoadingWorkers = 'loading-workers';
    case Vehicles = 'vehicles';
    case Factories = 'factories';
    case CashAccounts = 'cash-accounts';
    case ExpenseCategories = 'expense-categories';

    public function model(): string
    {
        return match ($this) {
            self::Customers => Customer::class,
            self::LoadingWorkers => LoadingWorker::class,
            self::Vehicles => Vehicle::class,
            self::Factories => Factory::class,
            self::CashAccounts => CashAccount::class,
            self::ExpenseCategories => ExpenseCategory::class,
        };
    }

    public function title(): string
    {
        return match ($this) {
            self::Customers => 'Konsumen',
            self::LoadingWorkers => 'Anggota Muat',
            self::Vehicles => 'Mobil',
            self::Factories => 'Pabrik',
            self::CashAccounts => 'Akun Kas & Bank',
            self::ExpenseCategories => 'Kategori Pengeluaran',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Customers => 'Kelola pelanggan dan informasi kebun mereka.',
            self::LoadingWorkers => 'Kelola anggota yang bekerja dalam pemuatan sawit.',
            self::Vehicles => 'Catat mobil, kapasitas, dan supir default.',
            self::Factories => 'Kelola tujuan penjualan dan kontak pabrik.',
            self::CashAccounts => 'Kelola akun kas dan bank untuk pencatatan keuangan.',
            self::ExpenseCategories => 'Kelompokkan biaya operasional harian usaha.',
        };
    }

    /** Metadata form dari allowlist server; bukan nama tabel dari input pengguna. */
    public function fields(): array
    {
        $identity = $this === self::Vehicles
            ? ['key' => 'plate_number', 'label' => 'Plat nomor', 'type' => 'text', 'required' => true, 'maxLength' => 20]
            : ['key' => 'name', 'label' => 'Nama', 'type' => 'text', 'required' => true, 'maxLength' => 150];
        $fields = [$identity];

        $additional = match ($this) {
            self::Customers => [
                ['phone', 'Nomor telepon', 'tel', 30],
                ['address', 'Alamat', 'textarea', 1000],
                ['farm_location', 'Lokasi kebun', 'textarea', 1000],
                ['notes', 'Catatan', 'textarea', 2000],
            ],
            self::LoadingWorkers => [
                ['phone', 'Nomor telepon', 'tel', 30],
                ['address', 'Alamat', 'textarea', 1000],
                ['joined_on', 'Tanggal bergabung', 'date', null],
            ],
            self::Vehicles => [
                ['vehicle_type', 'Jenis mobil', 'text', 100],
                ['capacity_kg', 'Kapasitas (kg)', 'weight', null],
                ['default_driver_id', 'Supir default', 'select', null],
            ],
            self::Factories => [
                ['contact_name', 'Nama kontak', 'text', 150],
                ['phone', 'Nomor telepon', 'tel', 30],
                ['address', 'Alamat pabrik', 'textarea', 1000],
            ],
            self::CashAccounts => [['type', 'Jenis akun', 'select', null]],
            self::ExpenseCategories => [],
        };
        foreach ($additional as [$key, $label, $type, $max]) {
            $fields[] = [
                'key' => $key, 'label' => $label, 'type' => $type,
                'required' => $key === 'type', 'maxLength' => $max,
            ];
        }

        return $fields;
    }

    public function columns(): array
    {
        return match ($this) {
            self::Customers => ['name' => 'Konsumen', 'phone' => 'Telepon', 'farm_location' => 'Lokasi kebun'],
            self::LoadingWorkers => ['name' => 'Anggota muat', 'phone' => 'Telepon', 'joined_on' => 'Bergabung'],
            self::Vehicles => ['plate_number' => 'Plat nomor', 'vehicle_type' => 'Jenis', 'capacity_kg' => 'Kapasitas (kg)', 'driver_name' => 'Supir default'],
            self::Factories => ['name' => 'Pabrik', 'contact_name' => 'Kontak', 'phone' => 'Telepon'],
            self::CashAccounts => ['name' => 'Akun', 'type_label' => 'Jenis'],
            self::ExpenseCategories => ['name' => 'Kategori'],
        };
    }

    public function searchable(): array
    {
        return $this === self::Vehicles ? ['code', 'plate_number', 'vehicle_type'] : ['code', 'name'];
    }
}
