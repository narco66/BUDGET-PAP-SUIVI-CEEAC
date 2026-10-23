<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Métadonnées rôles (système/métier, protégé) et permissions (module/action/libellé).
 * Daté après la migration Spatie (create_permission_tables) et rendu idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            if (! Schema::hasColumn('roles', 'type')) {
                $table->enum('type', ['systeme', 'metier'])->default('metier')->after('guard_name');
            }
            if (! Schema::hasColumn('roles', 'protege')) {
                $table->boolean('protege')->default(false)->after('type');
            }
            if (! Schema::hasColumn('roles', 'libelle')) {
                $table->string('libelle')->nullable()->after('protege');
            }
            if (! Schema::hasColumn('roles', 'description')) {
                $table->text('description')->nullable()->after('libelle');
            }
        });

        Schema::table('permissions', function (Blueprint $table) {
            if (! Schema::hasColumn('permissions', 'module')) {
                $table->string('module', 50)->nullable()->after('guard_name');
                $table->string('action', 50)->nullable()->after('module');
                $table->string('libelle')->nullable()->after('action');
            }
        });
    }

    public function down(): void
    {
        Schema::table('roles', fn (Blueprint $t) => $t->dropColumn(['type', 'protege', 'libelle', 'description']));
        Schema::table('permissions', fn (Blueprint $t) => $t->dropColumn(['module', 'action', 'libelle']));
    }
};
