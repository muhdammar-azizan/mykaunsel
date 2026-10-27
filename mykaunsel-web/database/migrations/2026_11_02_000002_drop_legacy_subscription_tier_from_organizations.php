<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The plain-text `organizations.subscription_tier` is replaced by a real plan (organizations.current_assignment_id).
 *
 * Nothing is dropped while any organization still has a tier text that no plan took over. In that case this migration
 * stops BEFORE changing anything and lists the organization ids. Map them first:
 *
 *     php artisan pricing:map-legacy-tiers --dry-run     (look)
 *     php artisan pricing:map-legacy-tiers                (assign the plan with the same name, as a normal assignment)
 *
 * or clear the text yourself when it is only leftover test data, then run `php artisan migrate` again.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('organizations', 'subscription_tier')) {
            return;
        }

        $unmapped = DB::table('organizations')
            ->whereNotNull('subscription_tier')
            ->whereRaw("trim(subscription_tier) <> ''")
            ->whereNull('current_assignment_id')
            ->orderBy('id')
            ->pluck('id');

        if ($unmapped->isNotEmpty()) {
            throw new RuntimeException(
                'Cannot remove organizations.subscription_tier yet: '.$unmapped->count().' organization(s) still have a tier text that is not linked to a plan '
                .'(organization ids: '.$unmapped->implode(', ').'). Nothing was changed. '
                .'Run "php artisan pricing:map-legacy-tiers --dry-run" to see what can be mapped, then "php artisan pricing:map-legacy-tiers", or clear the text, and run "php artisan migrate" again.'
            );
        }

        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn('subscription_tier');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('organizations', 'subscription_tier')) {
            Schema::table('organizations', function (Blueprint $table) {
                $table->string('subscription_tier')->nullable();
            });
        }
    }
};
