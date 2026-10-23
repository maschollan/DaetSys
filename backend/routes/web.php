<?php

use App\Http\Middleware\EnsureDocsAccess;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

/*
|--------------------------------------------------------------------------
| Dokumentasi API
|--------------------------------------------------------------------------
|
| Halaman dokumentasi interaktif (Scalar API Reference) yang memuat
| penjelasan endpoint, parameter, form coba request, dan hasil respons
| secara langsung dari dokumen OpenAPI.
|
| Halaman ini hanya menampilkan berkas statis, bukan view Blade, sehingga
| backend tetap bersifat API-only. Dokumen OpenAPI-nya dilayani pada
| `/docs/api.json` oleh Scramble.
|
*/

Route::get('/docs', function () {
    return response()->file(public_path('api-docs/index.html'));
})
    ->middleware(['web', EnsureDocsAccess::class])
    ->name('api-docs');
