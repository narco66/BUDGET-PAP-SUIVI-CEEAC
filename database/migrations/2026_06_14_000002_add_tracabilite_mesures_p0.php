<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Phase P0 Suivi Indicateurs — traçabilité validation et versioning des mesures. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mesures', function (Blueprint $table) {
            if (! Schema::hasColumn('mesures', 'verifie_par')) {
                $table->foreignId('verifie_par')->nullable()->after('saisi_par')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('mesures', 'verifie_le')) {
                $table->timestamp('verifie_le')->nullable()->after('verifie_par');
            }
            if (! Schema::hasColumn('mesures', 'valide_par')) {
                $table->foreignId('valide_par')->nullable()->after('verifie_le')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('mesures', 'valide_le')) {
                $table->timestamp('valide_le')->nullable()->after('valide_par');
            }
            if (! Schema::hasColumn('mesures', 'version')) {
                $table->unsignedInteger('version')->default(1)->after('statut');
            }
        });
    }

    public function down(): void
    {
        Schema::table('mesures', function (Blueprint $table) {
            if (Schema::hasColumn('mesures', 'verifie_par')) {
                $table->dropForeign(['verifie_par']);
            }
            if (Schema::hasColumn('mesures', 'valide_par')) {
                $table->dropForeign(['valide_par']);
            }
            $table->dropColumn(array_filter(
                ['verifie_par', 'verifie_le', 'valide_par', 'valide_le', 'version'],
                fn ($col) => Schema::hasColumn('mesures', $col)
            ));
        });
    }
};
