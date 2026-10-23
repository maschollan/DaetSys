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
    return response()->file(resource_path('api-docs/index.html'));
})
    ->middleware(['web', EnsureDocsAccess::class])
    ->name('api-docs');

/*
 * Halaman masuk untuk dokumentasi: publik, hanya berisi formulir
 * yang mengirim kredensial ke /api/docs/login lalu mengalihkan
 * ke /docs. Gerbang data tetap di middleware EnsureDocsAccess.
 */
Route::get('/docs/login', function () {
    return response()->file(resource_path('api-docs/login.html'));
})->name('api-docs.login');
