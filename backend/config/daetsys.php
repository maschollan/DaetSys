<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Idempotency
    |--------------------------------------------------------------------------
    |
    | Umur idempotency key dalam menit. Setelah melewati masa ini, key
    | yang sama boleh dipakai ulang untuk request baru.
    |
    */

    'idempotency_ttl' => (int) env('IDEMPOTENCY_TTL_MINUTES', 1440),

    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    |
    | Batas jumlah baris per halaman untuk seluruh endpoint list.
    |
    */

    'pagination' => [
        'per_page' => 15,
        'max_per_page' => 100,
    ],

    /*
    |--------------------------------------------------------------------------
    | Dashboard cache
    |--------------------------------------------------------------------------
    |
    | Lama ringkasan dashboard disimpan di cache (detik). Ringkasan
    | diinvalidasi setiap kali transaksi aset atau transfer berhasil.
    |
    */

    'dashboard_cache_ttl' => (int) env('DASHBOARD_CACHE_TTL', 60),

];
