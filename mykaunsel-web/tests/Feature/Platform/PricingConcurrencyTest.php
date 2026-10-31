<?php

namespace Tests\Feature\Platform;

use App\Models\Organization;
use App\Models\PromoCode;
use App\Models\SubscriptionAssignment;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The real race: several PROCESSES redeem the last use of a promo code at the same moment. This needs a database that really runs
 * transactions side by side (MySQL), so it is SKIPPED on SQLite, which is what the normal test run uses.
 *
 * It does NOT use RefreshDatabase (the child processes must see its rows, which are committed), so it creates its own rows with a
 * unique marker and removes them afterwards. It runs automatically whenever the suite runs on MySQL/MariaDB, against the throw-away test
 * database that the suite insists on (see tests/TestCase.php and docs/MYSQL_TESTING.md).
 */
class PricingConcurrencyTest extends TestCase
{
    private const PROCESSES = 6;

    private array $orgIds = [];

    private ?int $planId = null;

    private ?int $promoId = null;

    private ?int $adminId = null;

    protected function setUp(): void
    {
        parent::setUp();

        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped('Needs MySQL/MariaDB: SQLite cannot run two transactions at the same time. It runs by itself when the suite is run on MySQL (docs/MYSQL_TESTING.md).');
        }
    }

    protected function tearDown(): void
    {
        if ($this->orgIds !== []) {
            DB::table('organizations')->whereIn('id', $this->orgIds)->update(['current_assignment_id' => null]);
            DB::table('invoices')->whereIn('org_id', $this->orgIds)->delete(); // an invoice points at its organization (restrict)
            SubscriptionAssignment::query()->whereIn('org_id', $this->orgIds)->delete();
            Organization::query()->whereIn('id', $this->orgIds)->delete();
        }
        if ($this->promoId) {
            PromoCode::query()->whereKey($this->promoId)->delete();
        }
        if ($this->planId) {
            SubscriptionPlan::query()->whereKey($this->planId)->delete();
        }
        if ($this->adminId) {
            User::query()->whereKey($this->adminId)->delete();
        }

        parent::tearDown();
    }

    public function test_when_several_processes_redeem_the_last_use_exactly_one_succeeds(): void
    {
        $marker = 'CONC-'.bin2hex(random_bytes(4));

        $this->adminId = User::factory()->create()->id;
        $this->planId = SubscriptionPlan::create(['org_type' => 'university', 'name' => $marker, 'min_members' => 1, 'max_members' => null, 'price_cents' => 100000, 'active' => true, 'version' => 1])->id;
        $this->promoId = PromoCode::create([
            'code' => strtoupper($marker), 'type' => 'percent', 'value' => 10, 'valid_from' => now()->subDay(), 'valid_until' => now()->addDay(),
            'usage_limit' => 1, 'used_count' => 0, 'applies_to' => 'all', 'disabled' => false, 'version' => 1,
        ])->id;

        foreach (range(1, self::PROCESSES) as $i) {
            $this->orgIds[] = Organization::create(['name' => "{$marker} {$i}", 'org_type' => 'university', 'access_model' => 'open', 'subscription_status' => 'active'])->id;
        }

        $start = microtime(true) + 3.0;   // every child waits for this moment, then they all go at once
        $children = [];

        foreach ($this->orgIds as $orgId) {
            $cmd = [PHP_BINARY, base_path('tests/Support/redeem_child.php'), (string) $orgId, (string) $this->planId, (string) $this->adminId, strtoupper($marker), (string) $start];
            $process = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path());
            $children[] = [$process, $pipes];
        }

        $answers = [];
        foreach ($children as [$process, $pipes]) {
            $answers[] = trim(stream_get_contents($pipes[1]));
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);
        }

        $this->assertSame(1, count(array_keys($answers, 'OK', true)), 'exactly one process got the last use: '.json_encode($answers));
        $this->assertSame(self::PROCESSES - 1, count(array_keys($answers, 'REFUSED', true)), json_encode($answers));
        $this->assertSame(1, PromoCode::find($this->promoId)->used_count);
        $this->assertSame(1, SubscriptionAssignment::query()->whereIn('org_id', $this->orgIds)->whereNotNull('promo_code_id')->count());
    }
}
