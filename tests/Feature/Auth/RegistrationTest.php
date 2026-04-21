<?php

use App\Models\User;

it('returns 201 with token, refresh cookie, and user on successful registration', function () {
    $response = $this->postJson('/api/register', [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'Password1!',
        'password_confirmation' => 'Password1!',
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'access_token',
            'token_type',
            'expires_in',
            'user' => ['id', 'name', 'email', 'is_admin', 'two_factor_enabled'],
        ])
        ->assertJsonPath('token_type', 'Bearer');

    verifyCookie($response, 'refresh_token');
});

it('returns 422 on password_confirmation mismatch', function () {
    $response = $this->postJson('/api/register', [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'Password1!',
        'password_confirmation' => 'DifferentPassword1!',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['password']);
});

it('returns 422 on duplicate email', function () {
    User::factory()->create(['email' => 'jane@example.com']);

    $response = $this->postJson('/api/register', [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'Password1!',
        'password_confirmation' => 'Password1!',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

it('returns 422 on weak password (no mixed case)', function () {
    $response = $this->postJson('/api/register', [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'alllowercase1!',
        'password_confirmation' => 'alllowercase1!',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['password']);
});

it('persists user with is_admin=false and two_factor_enabled=false', function () {
    $this->postJson('/api/register', [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'Password1!',
        'password_confirmation' => 'Password1!',
    ]);

    $user = User::where('email', 'jane@example.com')->firstOrFail();

    expect($user->is_admin)->toBeFalse();
    expect($user->two_factor_enabled)->toBeFalse();
});
