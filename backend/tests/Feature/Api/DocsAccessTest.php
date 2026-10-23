<?php

use function Pest\Laravel\actingAs;

it('blocks docs for guests', function () {
    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10']);

    $this->getJson('/docs/api.json')->assertForbidden();
});

it('blocks docs for non-admin users', function () {
    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10']);
    $officer = actingAsRole('officer');

    actingAs($officer)->getJson('/docs/api.json')->assertForbidden();
});

it('allows docs for admin via bearer token', function () {
    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10']);
    $admin = actingAsRole('admin');

    actingAs($admin)->getJson('/docs/api.json')->assertOk();
});

it('opens docs for everyone when explicitly public', function () {
    config()->set('scramble.public_docs', true);

    $this->getJson('/docs/api.json')->assertOk();
});

it('allows admin to open the docs page itself', function () {
    $admin = actingAsRole('admin');

    actingAs($admin, 'sanctum')->get('/docs')->assertOk();
});
