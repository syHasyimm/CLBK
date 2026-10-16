<?php

return [
    'required' => ':attribute wajib diisi.',
    'email' => ':attribute harus berupa alamat email yang valid.',
    'string' => ':attribute harus berupa teks.',
    'confirmed' => 'Konfirmasi :attribute tidak sesuai.',
    'current_password' => 'Kata sandi saat ini tidak sesuai.',
    'unique' => ':attribute sudah digunakan.',
    'boolean' => ':attribute harus berupa status aktif atau nonaktif.',
    'integer' => ':attribute harus berupa bilangan bulat.',
    'in' => ':attribute tidak valid.',
    'enum' => ':attribute tidak valid.',
    'exists' => ':attribute tidak tersedia atau tidak aktif.',
    'regex' => 'Format :attribute tidak sesuai.',
    'date_format' => 'Format :attribute harus :format.',
    'prohibited' => ':attribute merupakan ketetapan sistem dan tidak dapat diubah.',
    'min' => ['string' => ':attribute minimal :min karakter.', 'numeric' => ':attribute minimal :min.'],
    'max' => ['string' => ':attribute maksimal :max karakter.', 'numeric' => ':attribute maksimal :max.'],
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
