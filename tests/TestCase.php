<?php

namespace Tests;

use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    /**
     * Seed roles/permissions (registration assigns the 'player' role)
     * once per run, right after RefreshDatabase finishes migrating.
     */
    protected bool $seed = true;

    protected string $seeder = RoleSeeder::class;

    /**
     * Tests use real titles as fixtures, so the titles the site hides (config/games.php `hidden`)
     * are shown here. GameCatalogueTest covers hiding and checks the real lists.
     */
    protected function setUp(): void
    {
        parent::setUp();

        config(['games.games.slots.hidden' => [], 'games.games.scratch.hidden' => []]);

        // The landing shows a random max_slides of its live hero slides; with room for all of them, landing tests never
        // depend on which were drawn. HeroSlidesTest sets its own cap to test the draw.
        config(['hero.max_slides' => 50]);
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
