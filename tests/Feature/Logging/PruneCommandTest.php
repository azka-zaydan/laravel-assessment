<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

it('deletes only log entries older than the specified number of days', function (): void {
    // 40 days old — should be pruned
    $oldId = makeApiLog([
        'created_at' => now()->subDays(40)->toDateTimeString(),
        'request_id' => Str::ulid(),
        'path' => 'api/old-40',
    ]);

    // 15 days old — should be retained
    $midId = makeApiLog([
        'created_at' => now()->subDays(15)->toDateTimeString(),
        'request_id' => Str::ulid(),
        'path' => 'api/mid-15',
    ]);

    // Today — should be retained
    $newId = makeApiLog([
        'created_at' => now()->toDateTimeString(),
        'request_id' => Str::ulid(),
        'path' => 'api/new-today',
    ]);

    $exitCode = $this->artisan('logs:prune', ['--days' => 30])
        ->assertSuccessful()
        ->run();

    // Old row gone
    expect(DB::table('api_logs')->where('id', $oldId)->exists())->toBeFalse();

    // Recent rows still present
    expect(DB::table('api_logs')->where('id', $midId)->exists())->toBeTrue();
    expect(DB::table('api_logs')->where('id', $newId)->exists())->toBeTrue();
});

it('outputs the count of deleted entries', function (): void {
    makeApiLog([
        'created_at' => now()->subDays(40)->toDateTimeString(),
        'request_id' => Str::ulid(),
        'path' => 'api/prune-me',
    ]);

    $this->artisan('logs:prune', ['--days' => 30])
        ->assertSuccessful()
        ->expectsOutputToContain('1');
});

it('does not delete rows when nothing is older than the threshold', function (): void {
    $recentId = makeApiLog([
        'created_at' => now()->subDays(5)->toDateTimeString(),
        'request_id' => Str::ulid(),
        'path' => 'api/keep-me',
    ]);

    $this->artisan('logs:prune', ['--days' => 30])
        ->assertSuccessful()
        ->expectsOutputToContain('0');

    expect(DB::table('api_logs')->where('id', $recentId)->exists())->toBeTrue();
});
