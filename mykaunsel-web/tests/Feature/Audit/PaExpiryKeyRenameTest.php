<?php

namespace Tests\Feature\Audit;

use App\Support\AuditCatalogue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PaExpiryKeyRenameTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_catalogue_has_the_new_key_and_not_the_old_one(): void
    {
        $this->assertArrayHasKey('counselor.pa_expired_suspended', config('audit.actions'));
        $this->assertArrayNotHasKey('system.pa_expiry_suspension', config('audit.actions'));

        $def = AuditCatalogue::describe('counselor.pa_expired_suspended');
        $this->assertSame(['counselors', 'suspended'], [$def['category'], $def['badge']]);
    }

    public function test_old_rows_are_converted_once_and_unknown_old_keys_still_display(): void
    {
        $row = fn (string $action, string $category) => DB::table('audit_logs')->insertGetId([
            'actor_type' => 'system', 'action' => $action, 'category' => $category, 'created_at' => now(),
        ]);
        $old = $row('system.pa_expiry_suspension', 'system');
        $other = $row('system.chat_purged', 'system');

        $migration = require database_path('migrations/2026_10_23_000002_rename_pa_expiry_audit_key.php');

        $this->assertSame(1, $migration->convert());
        $this->assertSame(['counselor.pa_expired_suspended', 'counselors'], [DB::table('audit_logs')->find($old)->action, DB::table('audit_logs')->find($old)->category]);
        $this->assertSame('system.chat_purged', DB::table('audit_logs')->find($other)->action, 'other keys untouched');
        $this->assertSame(0, $migration->convert(), 'second run changes nothing');

        // A key this code no longer knows still gets a readable label instead of breaking the page.
        $this->assertSame('System Pa Expiry Suspension', AuditCatalogue::describe('system.pa_expiry_suspension')['label']);
    }
}
