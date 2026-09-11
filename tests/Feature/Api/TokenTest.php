<?php

use App\Models\User;

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
