<?php

use Illuminate\Support\Facades\Artisan;

it('has generated types that match the current data objects', function () {
    $path = resource_path('js/types/generated.d.ts');
    $before = file_get_contents($path);

    Artisan::call('typescript:transform');

    $after = file_get_contents($path);

    expect($after)->toBe($before, 'Generated TypeScript is stale. Run: php artisan typescript:transform');
});
