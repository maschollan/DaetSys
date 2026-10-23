<?php

use App\Http\Controllers\Api\DocsAuthController;

it('sets a docs session cookie for admin login', function () {
    $admin = createUserWithRole('admin');

    $this->postJson('/api/docs/login', [
        'email' => $admin->email,
        'password' => 'password',
    ])->assertOk()
        ->assertCookie(DocsAuthController::COOKIE_NAME);
});

it('rejects docs login for non-admin', function () {
    $officer = createUserWithRole('officer');

    $this->postJson('/api/docs/login', [
        'email' => $officer->email,
        'password' => 'password',
    ])->assertForbidden()
        ->assertCookieMissing(DocsAuthController::COOKIE_NAME);
});

it('rejects docs login with wrong credentials', function () {
    $admin = createUserWithRole('admin');

    $this->postJson('/api/docs/login', [
        'email' => $admin->email,
        'password' => 'wrongpass1',
    ])->assertStatus(422);
});

it('rejects docs login for inactive admin accounts', function () {
    $admin = createUserWithRole('admin', ['is_active' => false]);

    $this->postJson('/api/docs/login', [
        'email' => $admin->email,
        'password' => 'password',
    ])->assertForbidden();
});

it('opens the docs page using the docs session cookie', function () {
    $admin = createUserWithRole('admin');

    $this->postJson('/api/docs/login', [
        'email' => $admin->email,
        'password' => 'password',
    ])->assertOk();

    // Payload dikirim sebagai plaintext: inilah isian cookie asli
    // (Enkripsi dilakukan runner test sebelum permintaan dikirim).
    $payload = json_encode([
        'sub' => $admin->id,
        'exp' => now()->addMinutes(5)->timestamp,
    ]);

    // getJson() tidak mengirim cookie (hanya withCredentials() yang
    // mengaktifkannya), jadi navigasi disimulasikan dengan get().
    $this->withCookies([DocsAuthController::COOKIE_NAME => $payload])
        ->get('/docs/api.json')
        ->assertOk();
});

it('rejects an expired docs session cookie', function () {
    $admin = createUserWithRole('admin');

    $payload = json_encode([
        'sub' => $admin->id,
        'exp' => now()->subMinutes(5)->timestamp,
    ]);

    $this->withCookies([DocsAuthController::COOKIE_NAME => $payload])
        ->get('/docs/api.json')
        ->assertForbidden();
});

it('clears the docs session cookie on logout', function () {
    $this->postJson('/api/docs/logout')
        ->assertOk()
        ->assertCookieExpired(DocsAuthController::COOKIE_NAME);
});
