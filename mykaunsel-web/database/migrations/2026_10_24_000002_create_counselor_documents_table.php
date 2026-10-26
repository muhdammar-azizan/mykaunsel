<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One row per counselor document (KB certificate, PA certificate, ID/passport) instead of three path columns on the
 * profile, so each document can carry its own review marker, note, upload time and retention state.
 * The existing paths (already on the private disk) are copied over, then the old columns are dropped so there is
 * only one source of truth.
 */
return new class extends Migration
{
    private const LEGACY = ['cert' => 'cert_document_path', 'pa' => 'pa_document_path', 'ic' => 'ic_document_path'];

    public function up(): void
    {
        Schema::create('counselor_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('counselor_profile_id')->constrained('counselor_profiles')->cascadeOnDelete();
            $table->string('type', 10);                          // cert | pa | ic
            $table->string('path')->nullable();                  // on the private disk; null once purged
            $table->string('original_name')->nullable();
            $table->unsignedInteger('size_bytes')->nullable();
            $table->string('status', 20)->default('unreviewed'); // unreviewed | ok | needs_replacement
            $table->string('note')->nullable();
            $table->dateTime('uploaded_at');
            $table->dateTime('purged_at')->nullable();
            $table->timestamps();

            $table->unique(['counselor_profile_id', 'type']);
            $table->index(['type', 'purged_at']);
        });

        $this->copyLegacyPaths();

        Schema::table('counselor_profiles', function (Blueprint $table) {
            $table->dropColumn(array_values(self::LEGACY));
        });
    }

    public function copyLegacyPaths(): int
    {
        $copied = 0;

        foreach (DB::table('counselor_profiles')->get() as $profile) {
            foreach (self::LEGACY as $type => $column) {
                if (blank($profile->{$column} ?? null)) {
                    continue;
                }

                DB::table('counselor_documents')->insert([
                    'counselor_profile_id' => $profile->id,
                    'type' => $type,
                    'path' => $profile->{$column},
                    'uploaded_at' => $profile->updated_at ?? now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $copied++;
            }
        }

        return $copied;
    }

    public function down(): void
    {
        Schema::table('counselor_profiles', function (Blueprint $table) {
            foreach (self::LEGACY as $column) {
                $table->string($column)->nullable();
            }
        });

        Schema::dropIfExists('counselor_documents');
    }
};
