<?php

namespace App\Support;

use Illuminate\Validation\Rules\File;

final class ProofUploadRules
{
    public static function proof(): array
    {
        return ['required', File::types(['pdf', 'jpg', 'jpeg', 'png'])->min(1 / 1024)->max(10240)];
    }
}
