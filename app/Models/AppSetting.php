<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['key', 'value_json', 'updated_by'])]
class AppSetting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return ['value_json' => 'json'];
    }

    public static function defaults(): array
    {
        return [
            'business_name' => 'CLBK TOKE',
            'business_timezone' => 'Asia/Jakarta',
            'weight_tolerance_basis_points' => 200,
            'receipt_prefix' => 'CLBK',
        ];
    }

    public static function values(): array
    {
        return array_replace(self::defaults(), self::query()->pluck('value_json', 'key')->all());
    }
}
