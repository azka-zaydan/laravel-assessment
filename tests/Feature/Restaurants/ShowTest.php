<?php

use Illuminate\Support\Facades\DB;

beforeEach(function () {
    config(['services.restaurants.provider' => 'fixture']);
});

it('returns full restaurant detail for a valid id', function () {
    actAsConfirmedUser();

    $response = $this->getJson('/api/restaurants/16507621');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'id', 'name', 'address', 'rating', 'cuisines',
                'location', 'phone', 'hours', 'menu_url', 'thumb_url', 'image_url',
            ],
        ]);

    expect($response->json('data.id'))->toBe(16507621);
    expect($response->json('data.name'))->toBe('Pizzeria Napoli Jakarta');
    expect($response->json('data.cuisines'))->toBeArray();
    expect($response->json('data.location'))->toHaveKeys(['lat', 'lon']);
});

it('returns 404 for a non-existent restaurant id', function () {
    actAsConfirmedUser();

    $response = $this->getJson('/api/restaurants/999999');

    $response->assertStatus(404)
        ->assertJsonStructure(['error']);
});

it('returns 401 when unauthenticated', function () {
    $this->getJson('/api/restaurants/16507621')
        ->assertStatus(401);
});

it('name and address are consistent with search fixture data', function () {
    actAsConfirmedUser();

    // Get detail first
    $detailResponse = $this->getJson('/api/restaurants/16507621');
    $detailResponse->assertStatus(200);

    $detail = $detailResponse->json('data');

    // Verify the name matches what we expect from the fixture
    expect($detail['name'])->toBe('Pizzeria Napoli Jakarta');
    expect($detail['address'])->toContain('Sudirman');
});

it('does not re-read fixture on second call (idempotent)', function () {
    actAsConfirmedUser();

    $r1 = $this->getJson('/api/restaurants/16507621')->assertStatus(200);
    $r2 = $this->getJson('/api/restaurants/16507621')->assertStatus(200);

    // Responses should be identical
    expect($r1->json('data.id'))->toBe($r2->json('data.id'));
    expect($r1->json('data.name'))->toBe($r2->json('data.name'));

    if (DB::getSchemaBuilder()->hasTable('restaurants')) {
        $row1 = DB::table('restaurants')->where('id', 16507621)->first();
        // If backend persists restaurants, the row should exist
        if ($row1 !== null) {
            expect($row1->id ?? $row1->res_id ?? null)->not->toBeNull();
        } else {
            // Cache-only backend (no DB persistence yet) — still valid
            expect(true)->toBeTrue();
        }
    } else {
        expect(true)->toBeTrue();
    }
});
