<?php

/*
|--------------------------------------------------------------------------
| Pesan Validasi
|--------------------------------------------------------------------------
|
| Tanpa berkas ini pesan galat keluar dalam bahasa Inggris, atau — bila
| APP_FALLBACK_LOCALE ikut disetel id seperti pada .env.example — keluar
| sebagai kunci mentah semacam "validation.required".
|
*/

return [
    'accepted' => ':attribute harus disetujui.',
    'active_url' => ':attribute bukan URL yang sah.',
    'after' => ':attribute harus tanggal setelah :date.',
    'after_or_equal' => ':attribute harus tanggal :date atau sesudahnya.',
    'alpha' => ':attribute hanya boleh berisi huruf.',
    'alpha_dash' => ':attribute hanya boleh berisi huruf, angka, tanda hubung, dan garis bawah.',
    'alpha_num' => ':attribute hanya boleh berisi huruf dan angka.',
    'array' => ':attribute harus berupa daftar.',
    'before' => ':attribute harus tanggal sebelum :date.',
    'before_or_equal' => ':attribute harus tanggal :date atau sebelumnya.',
    'between' => [
        'array' => ':attribute harus berisi antara :min sampai :max item.',
        'file' => ':attribute harus berukuran antara :min sampai :max kilobyte.',
        'numeric' => ':attribute harus bernilai antara :min sampai :max.',
        'string' => ':attribute harus terdiri dari :min sampai :max karakter.',
    ],
    'boolean' => ':attribute hanya boleh berisi ya atau tidak.',
    'confirmed' => 'Konfirmasi :attribute tidak cocok.',
    'current_password' => 'Password saat ini tidak sesuai.',
    'date' => ':attribute bukan tanggal yang sah.',
    'date_equals' => ':attribute harus tanggal :date.',
    'date_format' => 'Format :attribute tidak sesuai dengan :format.',
    'declined' => ':attribute harus ditolak.',
    'different' => ':attribute dan :other harus berbeda.',
    'digits' => ':attribute harus terdiri dari :digits angka.',
    'digits_between' => ':attribute harus terdiri dari :min sampai :max angka.',
    'email' => ':attribute harus berupa alamat email yang sah.',
    'ends_with' => ':attribute harus diakhiri salah satu dari: :values.',
    'exists' => ':attribute yang dipilih tidak sah.',
    'file' => ':attribute harus berupa berkas.',
    'filled' => ':attribute wajib diisi.',
    'gt' => [
        'array' => ':attribute harus berisi lebih dari :value item.',
        'file' => ':attribute harus lebih besar dari :value kilobyte.',
        'numeric' => ':attribute harus lebih besar dari :value.',
        'string' => ':attribute harus lebih panjang dari :value karakter.',
    ],
    'gte' => [
        'array' => ':attribute harus berisi :value item atau lebih.',
        'file' => ':attribute harus :value kilobyte atau lebih.',
        'numeric' => ':attribute harus :value atau lebih.',
        'string' => ':attribute harus :value karakter atau lebih.',
    ],
    'image' => ':attribute harus berupa gambar.',
    'in' => ':attribute yang dipilih tidak sah.',
    'in_array' => ':attribute tidak ada di dalam :other.',
    'integer' => ':attribute harus berupa bilangan bulat.',
    'ip' => ':attribute harus berupa alamat IP yang sah.',
    'json' => ':attribute harus berupa JSON yang sah.',
    'lowercase' => ':attribute harus huruf kecil.',
    'lt' => [
        'array' => ':attribute harus berisi kurang dari :value item.',
        'file' => ':attribute harus lebih kecil dari :value kilobyte.',
        'numeric' => ':attribute harus lebih kecil dari :value.',
        'string' => ':attribute harus lebih pendek dari :value karakter.',
    ],
    'lte' => [
        'array' => ':attribute tidak boleh berisi lebih dari :value item.',
        'file' => ':attribute harus :value kilobyte atau kurang.',
        'numeric' => ':attribute harus :value atau kurang.',
        'string' => ':attribute harus :value karakter atau kurang.',
    ],
    'max' => [
        'array' => ':attribute tidak boleh berisi lebih dari :max item.',
        'file' => ':attribute tidak boleh lebih besar dari :max kilobyte.',
        'numeric' => ':attribute tidak boleh lebih besar dari :max.',
        'string' => ':attribute tidak boleh lebih dari :max karakter.',
    ],
    'min' => [
        'array' => ':attribute harus berisi sedikitnya :min item.',
        'file' => ':attribute harus sedikitnya :min kilobyte.',
        'numeric' => ':attribute harus sedikitnya :min.',
        'string' => ':attribute harus sedikitnya :min karakter.',
    ],
    'not_in' => ':attribute yang dipilih tidak sah.',
    'not_regex' => 'Format :attribute tidak sah.',
    'numeric' => ':attribute harus berupa angka.',
    'password' => [
        'letters' => ':attribute harus memuat sedikitnya satu huruf.',
        'mixed' => ':attribute harus memuat huruf besar dan huruf kecil.',
        'numbers' => ':attribute harus memuat sedikitnya satu angka.',
        'symbols' => ':attribute harus memuat sedikitnya satu simbol.',
        'uncompromised' => ':attribute pernah bocor dalam kebocoran data. Pilih yang lain.',
    ],
    'present' => ':attribute wajib ada.',
    'prohibited' => ':attribute tidak boleh diisi.',
    'regex' => 'Format :attribute tidak sah.',
    'required' => ':attribute wajib diisi.',
    'required_if' => ':attribute wajib diisi bila :other bernilai :value.',
    'required_unless' => ':attribute wajib diisi kecuali :other bernilai :values.',
    'required_with' => ':attribute wajib diisi bila ada :values.',
    'required_without' => ':attribute wajib diisi bila tidak ada :values.',
    'same' => ':attribute dan :other harus sama.',
    'size' => [
        'array' => ':attribute harus berisi :size item.',
        'file' => ':attribute harus berukuran :size kilobyte.',
        'numeric' => ':attribute harus bernilai :size.',
        'string' => ':attribute harus terdiri dari :size karakter.',
    ],
    'starts_with' => ':attribute harus diawali salah satu dari: :values.',
    'string' => ':attribute harus berupa teks.',
    'unique' => ':attribute sudah dipakai.',
    'uploaded' => ':attribute gagal diunggah.',
    'uppercase' => ':attribute harus huruf besar.',
    'url' => 'Format :attribute tidak sah.',

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'custom-message',
        ],
    ],

    /*
    | Nama ruas dalam bahasa sehari-hari. Tanpa ini pesan galat menyebut
    | nama kolom basis data, misalnya "estimated_liter wajib diisi".
    */
    'attributes' => [
        'actual_liter' => 'Volume aktual',
        'address' => 'Alamat',
        'capacity_liter' => 'Kapasitas',
        'code' => 'Kode',
        'current_password' => 'Password saat ini',
        'destination' => 'Tujuan penyaluran',
        'dispute_reason' => 'Alasan keberatan',
        'dispute_resolution' => 'Tanggapan',
        'distributed_at' => 'Tanggal penyaluran',
        'effective_date' => 'Tanggal berlaku',
        'email' => 'Email',
        'estimated_liter' => 'Estimasi volume',
        'fee' => 'Ongkir',
        'latitude' => 'Garis lintang',
        'longitude' => 'Garis bujur',
        'max_distance_km' => 'Jarak maksimal',
        'method' => 'Metode setoran',
        'min_distance_km' => 'Jarak minimal',
        'name' => 'Nama',
        'notes' => 'Catatan',
        'partner_id' => 'Mitra',
        'partner_ids' => 'Mitra',
        'password' => 'Password',
        'password_confirmation' => 'Konfirmasi password',
        'payment_method' => 'Cara pembayaran',
        'payment_status' => 'Status pembayaran',
        'phone' => 'Telepon',
        'pickup_date' => 'Tanggal penjemputan',
        'pickup_time' => 'Jam penjemputan',
        'price_per_liter' => 'Harga per liter',
        'rejection_reason' => 'Alasan penolakan',
        'role' => 'Peran',
        'status' => 'Status',
        'type' => 'Jenis',
        'volume_liter' => 'Volume',
    ],
];
