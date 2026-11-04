<?php

namespace Tests\Feature\Counselor;

use App\Models\CounselorKbAttempt;
use App\Models\CounselorProfile;
use App\Models\LkmDirectorySnapshot;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The real race: several PROCESSES sign up with the same KB number at the same moment. Exactly one account may be made; every other one must get a
 * clear message (never an error page) and leave nothing behind. This needs a database that really runs transactions side by side (MySQL), so it is
 * SKIPPED on SQLite, like PricingConcurrencyTest (see docs/MYSQL_TESTING.md for how to run the suite on MySQL).
 *
 * It does NOT use RefreshDatabase (the child processes must see its rows, which are committed), so it creates its own rows with a unique marker and
 * removes them afterwards. It needs the platform organization that `migrate --seed` makes.
 */
class CounselorSignupConcurrencyTest extends TestCase
{
    private const PROCESSES = 6;

    private string $marker = '';

    protected function setUp(): void
    {
        parent::setUp();

        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped('Needs MySQL/MariaDB: SQLite cannot run two transactions at the same time. It runs by itself when the suite is run on MySQL (docs/MYSQL_TESTING.md).');
        }
    }

    protected function tearDown(): void
    {
        if ($this->marker !== '') {
            $users = User::query()->where('email', 'like', "%{$this->marker}%")->pluck('id');
            $profiles = CounselorProfile::query()->whereIn('user_id', $users)->pluck('id');

            DB::table('audit_logs')->where('target_type', 'counselor')->whereIn('target_id', $profiles)->delete();
            CounselorKbAttempt::query()->where('kb_number', 'like', 'KB9%')->where('submitted_name', 'like', "%{$this->marker}%")->delete();
            DB::table('memberships')->whereIn('user_id', $users)->delete();
            CounselorProfile::query()->whereIn('id', $profiles)->delete();
            User::query()->whereIn('id', $users)->delete();
            LkmDirectorySnapshot::query()->where('full_name', 'like', "%{$this->marker}%")->delete();
        }

        parent::tearDown();
    }

    public function test_when_several_processes_sign_up_with_one_kb_number_exactly_one_account_is_made_and_nobody_sees_an_error_page(): void
    {
        $this->marker = 'conc'.bin2hex(random_bytes(3));
        $kb = 'KB9'.random_int(1000, 9999);
        $pa = 'PA9'.random_int(1000, 9999);
        $name = "Race {$this->marker}";

        LkmDirectorySnapshot::create(['kb_number' => $kb, 'pa_number' => $pa, 'full_name' => $name, 'status' => 'Aktif', 'pa_valid_from' => '2026-01-01', 'pa_valid_until' => now()->addYear()->toDateString()]);

        $start = microtime(true) + 4.0;   // every child waits for this moment, then they all go at once
        $children = [];

        foreach (range(1, self::PROCESSES) as $i) {
            $cmd = [PHP_BINARY, base_path('tests/Support/signup_child.php'), $kb, $pa, $name, "p{$i}-{$this->marker}@example.test", "198.51.100.{$i}", (string) $start];
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

        $this->assertSame(1, count(array_keys($answers, 'OK', true)), 'exactly one process signed up: '.json_encode($answers));
        $this->assertSame([], array_values(array_filter($answers, fn ($a) => str_starts_with($a, 'ERROR'))), 'no one saw an error page: '.json_encode($answers));
        $this->assertSame(self::PROCESSES - 1, count(array_filter($answers, fn ($a) => str_starts_with($a, 'REFUSED'))), json_encode($answers));

        $this->assertSame(1, CounselorProfile::where('kb_number', $kb)->count());
        $this->assertSame(1, User::where('email', 'like', "%{$this->marker}%")->count(), 'the refused sign ups left no account behind');
    }
}
