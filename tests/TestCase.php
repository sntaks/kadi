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
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
