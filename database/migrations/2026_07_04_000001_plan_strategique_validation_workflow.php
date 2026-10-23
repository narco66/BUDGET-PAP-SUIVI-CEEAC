<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Phase P3 — Workflow validation plans + traçabilité soumission/validation. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans_strategiques', function (Blueprint $table) {
            $table->timestamp('submitted_at')->nullable()->after('document_officiel');
            $table->timestamp('validated_at')->nullable()->after('submitted_at');
            $table->foreignId('validated_by')->nullable()->after('validated_at')->constrained('users')->nullOnDelete();
            $table->text('motif_rejet')->nullable()->after('validated_by');
        });
    }

    public function down(): void
    {
        Schema::table('plans_strategiques', function (Blueprint $table) {
            $table->dropConstrainedForeignId('validated_by');
            $table->dropColumn(['submitted_at', 'validated_at', 'motif_rejet']);
        });
    }
};
