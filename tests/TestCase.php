<?php

namespace Tests;

use App\Events\MessageCreated;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Event;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Broadcasting a message for real would attempt network I/O to Reverb, which
        // is not running during the test suite. Delivery is verified manually (task 7
        // step 12), not by this suite. A test asserting on MessageCreated itself can
        // still call Event::fake([MessageCreated::class]) again to make its intent
        // explicit; the calls compose.
        Event::fake([MessageCreated::class]);
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
