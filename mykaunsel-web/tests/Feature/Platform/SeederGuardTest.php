<?php

namespace Tests\Feature\Platform;

use App\Models\Organization;
use App\Models\User;
use Database\Seeders\Concerns\RefusesOutsideDevelopment;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/**
 * No seeder that makes demo data (accounts with a well-known password, sample records) can run on a real server.
 */
class SeederGuardTest extends TestCase
{
    use RefreshDatabase;

    /** Sample seeders that are run by hand with --class and are not part of `db:seed`. They are guarded all the same. */
    private const MANUAL = ['DirectorySampleSeeder'];

    /** @return list<class-string<Seeder>> */
    private function seeders(): array
    {
        $classes = [];

        foreach (glob(database_path('seeders/*Seeder.php')) as $file) {
            $classes[] = 'Database\\Seeders\\'.basename($file, '.php');
        }

        sort($classes);

        return $classes;
    }

    private function counts(): array
    {
        return collect(Schema::getTableListing(schemaQualified: false))
            ->reject(fn ($table) => $table === 'migrations')
            ->mapWithKeys(fn ($table) => [$table => DB::table($table)->count()])
            ->all();
    }

    public function test_every_seeder_refuses_outside_local_and_testing_and_creates_nothing(): void
    {
        $before = $this->counts();

        foreach (['production', 'staging', 'live'] as $environment) {
            $this->app['env'] = $environment;

            foreach ($this->seeders() as $class) {
                try {
                    app()->call([app($class), 'run']);
                    $this->fail("{$class} ran in {$environment}");
                } catch (RuntimeException $e) {
                    $this->assertStringContainsString('Nothing was created', $e->getMessage(), $class);
                    $this->assertMatchesRegularExpression('/local and testing/', $e->getMessage(), $class);
                }
            }
        }

        $this->assertSame($before, $this->counts(), 'not a single row was created by any seeder');
        $this->assertSame(0, User::count());
        $this->assertSame(0, Organization::count());
    }

    public function test_every_seeder_uses_the_guard_so_a_new_one_cannot_be_forgotten(): void
    {
        foreach ($this->seeders() as $class) {
            $this->assertContains(RefusesOutsideDevelopment::class, class_uses_recursive($class), "{$class} must use RefusesOutsideDevelopment");
        }
    }

    public function test_every_seeder_is_listed_in_the_full_seed_or_marked_manual(): void
    {
        $listed = [...DatabaseSeeder::BASE_SEEDERS, ...DatabaseSeeder::DEMO_SEEDERS];

        foreach ($this->seeders() as $class) {
            $short = class_basename($class);

            if ($short === 'DatabaseSeeder') {
                continue;
            }

            $this->assertTrue(
                in_array($class, $listed, true) || in_array($short, self::MANUAL, true),
                "{$short} is in neither DatabaseSeeder nor the manual list: decide which it is"
            );
        }

        $this->assertSame([], DatabaseSeeder::BASE_SEEDERS, 'a base seeder must never create an account with a known password; this test is the reminder to check');
    }

    public function test_the_full_seed_stops_on_a_real_server_before_the_first_seeder(): void
    {
        $this->app['env'] = 'production';

        try {
            // (the db:seed command itself also asks for --force in production; this is the seeder's own guard)
            app(DatabaseSeeder::class)->setContainer(app())->run();
            $this->fail('DatabaseSeeder ran in production');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('platform:create-admin', $e->getMessage());
        }

        $this->assertSame(0, User::count());
        $this->assertSame(0, Organization::count());
    }

    public function test_the_full_seed_still_works_in_the_local_environment(): void
    {
        $this->app['env'] = 'local';

        $this->seed(DatabaseSeeder::class);

        $this->assertSame(1, User::where('email', 'admin@mykaunsel.com')->count());
        $this->assertGreaterThan(1, Organization::count());
    }
}
