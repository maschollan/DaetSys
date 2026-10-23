<?php

it('stores the docs admin session for the documentation page', function () {
    $admin = createUserWithRole('admin');

    $this->postJson('/docs/login', [
        'email' => $admin->email,
        'password' => 'password',
    ])->assertOk()
        ->assertJson(['redirect' => '/docs'])
        ->assertSessionHas('docs_admin_id', $admin->id);
});

it('rejects docs login for non-admin', function () {
    $officer = createUserWithRole('officer');

    $this->postJson('/docs/login', [
        'email' => $officer->email,
        'password' => 'password',
    ])->assertForbidden()
        ->assertSessionMissing('docs_admin_id');
});

it('rejects docs login with wrong credentials', function () {
    $admin = createUserWithRole('admin');

    $this->postJson('/docs/login', [
        'email' => $admin->email,
        'password' => 'wrongpass1',
    ])->assertStatus(422)
        ->assertSessionMissing('docs_admin_id');
});

it('rejects docs login for inactive admin accounts', function () {
    $admin = createUserWithRole('admin', ['is_active' => false]);

    $this->postJson('/docs/login', [
        'email' => $admin->email,
        'password' => 'password',
    ])->assertForbidden()
        ->assertSessionMissing('docs_admin_id');
});

it('opens the documentation page using the docs admin session', function () {
    $admin = createUserWithRole('admin');

    $this->withSession(['docs_admin_id' => $admin->id])
        ->get('/docs')
        ->assertOk();
});

it('rejects a docs admin session for an unknown user', function () {
    $admin = createUserWithRole('admin');

    $this->withSession(['docs_admin_id' => $admin->id + 999])
        ->get('/docs')
        ->assertForbidden();
});

it('clears the docs admin session on logout', function () {
    $admin = createUserWithRole('admin');

    $this->postJson('/docs/login', [
        'email' => $admin->email,
        'password' => 'password',
    ]);

    $this->postJson('/docs/logout')
        ->assertOk()
        ->assertJson(['redirect' => '/'])
        ->assertSessionMissing('docs_admin_id');
});
