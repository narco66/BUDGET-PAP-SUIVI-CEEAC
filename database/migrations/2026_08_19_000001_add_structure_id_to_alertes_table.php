<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alertes', function (Blueprint $table): void {
            if (! Schema::hasColumn('alertes', 'structure_id')) {
                $table->foreignId('structure_id')
                    ->nullable()
                    ->after('exercice_id')
                    ->constrained('structures')
                    ->nullOnDelete();

                $table->index(['structure_id', 'statut']);
            }
        });

        if (Schema::hasTable('activites') && Schema::hasColumn('activites', 'structure_id')) {
            DB::table('alertes')
                ->join('activites', 'alertes.objet_id', '=', 'activites.id')
                ->select('alertes.id', 'activites.structure_id')
                ->whereIn('alertes.objet_type', ['App\\Models\\Activite', 'activite'])
                ->whereNull('alertes.structure_id')
                ->whereNotNull('activites.structure_id')
                ->orderBy('alertes.id')
                ->chunkById(200, function ($alertes): void {
                    foreach ($alertes as $alerte) {
                        DB::table('alertes')
                            ->where('id', $alerte->id)
                            ->update(['structure_id' => $alerte->structure_id]);
                    }
                }, 'alertes.id', 'id');
        }
    }

    public function down(): void
    {
        Schema::table('alertes', function (Blueprint $table): void {
            if (Schema::hasColumn('alertes', 'structure_id')) {
                $table->dropConstrainedForeignId('structure_id');
            }
        });
    }
};
