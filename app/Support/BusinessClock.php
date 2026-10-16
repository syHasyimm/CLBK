<?php

namespace App\Support;

use App\Models\AppSetting;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class BusinessClock
{
    public function timezone(): string
    {
        return AppSetting::values()['business_timezone'];
    }

    public function now(): CarbonImmutable
    {
        return CarbonImmutable::now('UTC')->startOfSecond();
    }

    public function date(CarbonImmutable $time): string
    {
        return $time->setTimezone($this->timezone())->toDateString();
    }

    public function local(CarbonImmutable $time): string
    {
        return $time->setTimezone($this->timezone())->format('Y-m-d\TH:i:s');
    }

    public function parse(string $value, string $field = 'effective_from'): CarbonImmutable
    {
        if (! preg_match('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(?::\d{2})?\z/', $value)) {
            throw ValidationException::withMessages([$field => 'Tanggal dan waktu WIB tidak valid.']);
        }
        try {
            $format = strlen($value) === 16 ? 'Y-m-d\TH:i' : 'Y-m-d\TH:i:s';
            $date = CarbonImmutable::createFromFormat('!'.$format, $value, $this->timezone());
            if ($date->format($format) !== $value) {
                throw new \InvalidArgumentException;
            }

            return $date->utc();
        } catch (\Throwable) {
            throw ValidationException::withMessages([$field => 'Tanggal dan waktu WIB tidak valid.']);
        }
    }
}
