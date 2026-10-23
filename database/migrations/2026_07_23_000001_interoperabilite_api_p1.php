<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Phase I-P1 — registre applications connectées et clients API. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('connected_apps', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('libelle');
            $table->text('description')->nullable();
            $table->string('type', 30)->default('interne');
            $table->string('contact_email')->nullable();
            $table->json('scopes_autorises');
            $table->string('statut', 20)->default('actif');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('statut');
        });

        Schema::create('api_clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('connected_app_id')->constrained('connected_apps')->cascadeOnDelete();
            $table->string('client_id', 64)->unique();
            $table->string('client_secret_hash');
            $table->string('libelle')->default('Production');
            $table->string('statut', 20)->default('actif');
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('derniere_utilisation_le')->nullable();
            $table->timestamps();

            $table->index(['connected_app_id', 'statut']);
        });

        Schema::table('personal_access_tokens', function (Blueprint $table) {
            if (! Schema::hasColumn('personal_access_tokens', 'connected_app_id')) {
                $table->unsignedBigInteger('connected_app_id')->nullable()->after('abilities');
                $table->index('connected_app_id');
            }
            if (! Schema::hasColumn('personal_access_tokens', 'api_client_id')) {
                $table->unsignedBigInteger('api_client_id')->nullable()->after('connected_app_id');
                $table->index('api_client_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            foreach (['api_client_id', 'connected_app_id'] as $col) {
                if (Schema::hasColumn('personal_access_tokens', $col)) {
                    $table->dropIndex([$col]);
                    $table->dropColumn($col);
                }
            }
        });

        Schema::dropIfExists('api_clients');
        Schema::dropIfExists('connected_apps');
    }
};
