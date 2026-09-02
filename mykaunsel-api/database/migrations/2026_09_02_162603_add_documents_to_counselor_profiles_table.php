<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('counselor_profiles', function (Blueprint $table) {
            $table->string('cert_document_path')->nullable()->after('verification_status');
            $table->string('pa_document_path')->nullable()->after('cert_document_path');
            $table->string('ic_document_path')->nullable()->after('pa_document_path');
            $table->text('rejection_reason')->nullable()->after('ic_document_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('counselor_profiles', function (Blueprint $table) {
            $table->dropColumn(['cert_document_path', 'pa_document_path', 'ic_document_path', 'rejection_reason']);
        });
    }
};
