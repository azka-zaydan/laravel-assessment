<?php

use App\Models\User;
use Illuminate\Support\Str;

beforeEach(function (): void {
    config(['queue.default' => 'sync']);
});

it('returns 403 for a non-admin authenticated user', function (): void {
    $user = User::factory()->withTwoFactor()->create(['is_admin' => false]);
    $user->forceFill(['two_factor_confirmed_at' => now()])->save();
    $token = $user->createToken('test')->accessToken;

    $this->withToken($token)
        ->getJson('/api/admin/api-logs')
        ->assertStatus(403);
});

it('returns 200 with paginated data for admin with 2FA confirmed', function (): void {
    makeAdmin();

    $this->getJson('/api/admin/api-logs')
        ->assertOk()
        ->assertJsonStructure([
            'data',
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            'links' => ['first', 'last', 'prev', 'next'],
        ]);
});

it('returns 422 (not 500) for a non-numeric filter[response_status]', function (): void {
    // Regression: the underlying AllowedFilter::exact('response_status') would
    // pass "abc" to Postgres which rejects it with SQLSTATE[22P02] invalid
    // input syntax for type smallint — surfacing as a 500. ApiLogIndexRequest
    // validates the input at the boundary and we should see a clean 422.
    makeAdmin();

    $this->getJson('/api/admin/api-logs?filter[response_status]=not-a-number')
        ->assertStatus(422)
        ->assertJsonValidationErrors('filter.response_status');
});

it('returns 422 for out-of-range filter[response_status]', function (): void {
    makeAdmin();

    $this->getJson('/api/admin/api-logs?filter[response_status]=999')
        ->assertStatus(422)
        ->assertJsonValidationErrors('filter.response_status');
});

it('respects X-Forwarded-Proto=https for pagination links (TrustProxies)', function (): void {
    // Regression: without TrustProxies configured, Laravel's URL generator
    // emits http:// pagination links even when the request came in over
    // https://. Cloudflare → Railway prod chain depends on this.
    makeAdmin();

    $response = $this->withHeaders([
        'X-Forwarded-Proto' => 'https',
        'X-Forwarded-Host' => 'laravel.catatkeu.app',
    ])->getJson('/api/admin/api-logs');

    $response->assertOk();
    expect($response->json('links.first'))->toStartWith('https://');
});

it('filters by method=POST and returns only POST logs', function (): void {
    makeAdmin();

    makeApiLog(['method' => 'POST', 'path' => 'api/login', 'request_id' => Str::ulid()]);
    makeApiLog(['method' => 'GET', 'path' => 'api/me', 'request_id' => Str::ulid()]);

    $response = $this->getJson('/api/admin/api-logs?filter[method]=POST')
        ->assertOk();

    $data = $response->json('data');

    expect(collect($data)->every(fn ($row) => $row['method'] === 'POST'))->toBeTrue();
    expect(collect($data)->contains(fn ($row) => $row['method'] === 'GET'))->toBeFalse();
});

it('filters by response_status=422 and returns only 422 logs', function (): void {
    makeAdmin();

    makeApiLog(['response_status' => 422, 'path' => 'api/login', 'request_id' => Str::ulid()]);
    makeApiLog(['response_status' => 200, 'path' => 'api/me', 'request_id' => Str::ulid()]);

    // The Spatie QueryBuilder key matches the column name: response_status
    $response = $this->getJson('/api/admin/api-logs?filter[response_status]=422')
        ->assertOk();

    $data = $response->json('data');

    expect($data)->not->toBeEmpty();
    expect(collect($data)->every(fn ($row) => $row['response_status'] === 422))->toBeTrue();
});

it('filters by user_id and returns only that user\'s logs', function (): void {
    makeAdmin();

    $targetUser = User::factory()->create();
    makeApiLog(['user_id' => $targetUser->id, 'path' => 'api/target', 'request_id' => Str::ulid()]);
    makeApiLog(['user_id' => null, 'path' => 'api/anon', 'request_id' => Str::ulid()]);

    $response = $this->getJson("/api/admin/api-logs?filter[user_id]={$targetUser->id}")
        ->assertOk();

    $data = $response->json('data');

    expect($data)->not->toBeEmpty();
    expect(collect($data)->every(fn ($row) => $row['user_id'] === $targetUser->id))->toBeTrue();
});

it('filters by path containing "login"', function (): void {
    makeAdmin();

    makeApiLog(['path' => 'api/login', 'request_id' => Str::ulid()]);
    makeApiLog(['path' => 'api/me', 'request_id' => Str::ulid()]);

    $response = $this->getJson('/api/admin/api-logs?filter[path]=login')
        ->assertOk();

    $data = $response->json('data');

    expect($data)->not->toBeEmpty();
    expect(collect($data)->every(fn ($row) => str_contains($row['path'], 'login')))->toBeTrue();
});

it('filters by from date and excludes older entries', function (): void {
    makeAdmin();

    // Old log
    makeApiLog([
        'created_at' => now()->subDays(5)->toDateTimeString(),
        'path' => 'api/old',
        'request_id' => Str::ulid(),
    ]);

    // Recent log
    makeApiLog([
        'created_at' => now()->toDateTimeString(),
        'path' => 'api/recent',
        'request_id' => Str::ulid(),
    ]);

    // Use toDateTimeString() (Y-m-d H:i:s) rather than toIso8601String()
    // because the latter emits a `+HH:MM` timezone offset where `+` gets
    // URL-decoded to a space on the server side, corrupting the date.
    // Real clients should urlencode() the value or use a space-safe format.
    $from = urlencode(now()->subDays(2)->toDateTimeString());
    $response = $this->getJson("/api/admin/api-logs?filter[from]={$from}")
        ->assertOk();

    $data = $response->json('data');

    // All returned entries must be >= $from
    foreach ($data as $row) {
        expect(strtotime($row['created_at']))->toBeGreaterThanOrEqual(strtotime(now()->subDays(2)->toDateTimeString()) - 1);
    }

    // The old log should not appear
    $paths = collect($data)->pluck('path');
    expect($paths->contains('api/old'))->toBeFalse();
});

it('sorts by -duration_ms (descending)', function (): void {
    makeAdmin();

    makeApiLog(['duration_ms' => 10, 'request_id' => Str::ulid()]);
    makeApiLog(['duration_ms' => 500, 'request_id' => Str::ulid()]);
    makeApiLog(['duration_ms' => 50, 'request_id' => Str::ulid()]);

    $response = $this->getJson('/api/admin/api-logs?sort=-duration_ms')
        ->assertOk();

    $durations = collect($response->json('data'))->pluck('duration_ms')->values();

    expect($durations->first())->toBeGreaterThanOrEqual($durations->last());

    // Check they are in descending order
    $sorted = $durations->sortDesc()->values();
    expect($durations->toArray())->toBe($sorted->toArray());
});

it('returns at most per_page=25 rows', function (): void {
    makeAdmin();

    // Seed 30 logs
    for ($i = 0; $i < 30; $i++) {
        makeApiLog(['request_id' => Str::ulid()]);
    }

    $response = $this->getJson('/api/admin/api-logs?per_page=25')
        ->assertOk();

    expect(count($response->json('data')))->toBeLessThanOrEqual(25);
    expect($response->json('meta.per_page'))->toBe(25);
});

it('clamps per_page=500 down to the maximum allowed', function (): void {
    makeAdmin();

    $response = $this->getJson('/api/admin/api-logs?per_page=500')
        ->assertOk();

    // Per the spec, max is 200
    expect($response->json('meta.per_page'))->toBeLessThanOrEqual(200);
});
