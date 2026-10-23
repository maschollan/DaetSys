<?php

use App\Http\Controllers\Web\DocsAuthController;
use App\Http\Middleware\EnsureDocsAccess;
use App\Support\StaticPage;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Halaman landing (`/`)
|--------------------------------------------------------------------------
|
| Halaman navigasi sederhana: menghitung mundur lalu otomatis mengalihkan
| ke frontend (dikonfigurasi `FRONTEND_URL`), dengan tombol "Batal" agar
| pengunjung tetap di halaman ini serta tombol ke frontend dan `/docs`.
|
*/
Route::get('/', function () {
    return StaticPage::render('landing/index.html', [
        '[[FRONTEND_URL]]' => (string) config('app.frontend_url'),
    ]);
})->name('home');

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
    return StaticPage::render('api-docs/index.html', [
        '[[CSRF_TOKEN]]' => csrf_token(),
    ]);
})
    ->middleware(['web', EnsureDocsAccess::class])
    ->name('api-docs');

/*
 * Halaman masuk untuk dokumentasi: publik, hanya berisi formulir
 * yang mengirim kredensial ke POST /docs/login lalu mengalihkan
 * ke /docs. Gerbang data tetap di middleware EnsureDocsAccess.
 */
Route::get('/docs/login', function () {
    return StaticPage::render('api-docs/login.html', [
        '[[CSRF_TOKEN]]' => csrf_token(),
    ]);
})->name('api-docs.login');

/*
 * Sesi masuk dokumentasi berbasis session Laravel. Pada environment
 * `testing` middleware CSRF otomatis dilewati Laravel; di browser,
 * halaman statis membawa tokennya lewat placeholder `[[CSRF_TOKEN]]`.
 */
Route::post('/docs/login', [DocsAuthController::class, 'login'])
    ->middleware('throttle:login')
    ->name('docs.login');

Route::post('/docs/logout', [DocsAuthController::class, 'logout'])
    ->name('docs.logout');

/*
 * Aset pustaka Scalar yang dimuat oleh halaman /docs. Berkas disimpan di
 * resources/api-docs (bukan public/) sehingga perlu dilayani lewat route,
 * dan isinya hanya kode pustaka publik — bukan dokumen OpenAPI yang
 * membutuhkan gerbang EnsureDocsAccess.
 */
Route::get('/api-docs/scalar.js', function () {
    return response()->file(resource_path('api-docs/scalar.js'), [
        'Content-Type' => 'application/javascript; charset=utf-8',
    ]);
})->name('api-docs.scalar');
