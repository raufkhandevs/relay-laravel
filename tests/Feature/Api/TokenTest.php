<?php

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

it('issues a token for correct credentials', function () {
    $user = User::factory()->create(['password' => bcrypt('correct-horse')]);

    $response = $this->postJson('/api/tokens', [
        'email' => $user->email,
        'password' => 'correct-horse',
        'device_name' => 'iPhone 17 Simulator',
    ]);

    $response->assertOk()->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'role']]);
    expect($user->fresh()->tokens)->toHaveCount(1);
});

it('refuses wrong credentials', function () {
    $user = User::factory()->create(['password' => bcrypt('correct-horse')]);

    $this->postJson('/api/tokens', [
        'email' => $user->email,
        'password' => 'wrong',
        'device_name' => 'iPhone 17 Simulator',
    ])->assertStatus(422);
});

it('revokes only the current token on logout', function () {
    $user = User::factory()->create();
    $keep = $user->createToken('desktop')->plainTextToken;
    $drop = $user->createToken('phone')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$drop}")
        ->deleteJson('/api/tokens/current')
        ->assertNoContent();

    expect($user->fresh()->tokens)->toHaveCount(1)
        ->and($user->fresh()->tokens->first()->name)->toBe('desktop');
});

it('throttles repeated failed logins for one email without blocking other emails', function () {
    $victim = User::factory()->create(['password' => bcrypt('correct-horse')]);
    $bystander = User::factory()->create(['password' => bcrypt('correct-horse')]);

    // Laravel's rate limiter cache persists across tests within a single run.
    // The throttle middleware hashes the limiter name and key together
    // (ThrottleRequests::$shouldHashKeys, on by default), so replicate that
    // hash here to clear the exact cache entry this attempt will use.
    $throttleKey = Str::transliterate(Str::lower($victim->email).'|127.0.0.1');
    RateLimiter::clear(md5('api-tokens'.$throttleKey));

    $attempt = fn (string $email, string $password) => $this->postJson('/api/tokens', [
        'email' => $email,
        'password' => $password,
        'device_name' => 'iPhone 17 Simulator',
    ]);

    for ($i = 0; $i < 5; $i++) {
        $attempt($victim->email, 'wrong')->assertStatus(422);
    }

    $attempt($victim->email, 'wrong')->assertStatus(429);

    $attempt($bystander->email, 'correct-horse')
        ->assertOk()
        ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'role']]);
});

it('logs out a session-authenticated request without erroring', function () {
    $user = User::factory()->create();
    $user->createToken('desktop');

    $this->actingAs($user, 'web')
        ->deleteJson('/api/tokens/current')
        ->assertNoContent();

    expect($user->fresh()->tokens)->toHaveCount(1);
});
