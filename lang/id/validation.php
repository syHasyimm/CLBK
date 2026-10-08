<?php

return [
    'required' => ':attribute wajib diisi.',
    'email' => ':attribute harus berupa alamat email yang valid.',
    'string' => ':attribute harus berupa teks.',
    'confirmed' => 'Konfirmasi :attribute tidak sesuai.',
    'current_password' => 'Kata sandi saat ini tidak sesuai.',
    'unique' => ':attribute sudah digunakan.',
    'min' => ['string' => ':attribute minimal :min karakter.'],
    'max' => ['string' => ':attribute maksimal :max karakter.'],
    'password' => [
        'letters' => ':attribute harus mengandung huruf.',
        'numbers' => ':attribute harus mengandung angka.',
    ],
    'attributes' => [
        'name' => 'Nama',
        'email' => 'Email',
        'password' => 'Kata sandi',
        'current_password' => 'Kata sandi saat ini',
        'password_confirmation' => 'Konfirmasi kata sandi',
    ],
];
