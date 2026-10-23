<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Phase A — enrichissement référentiel exercices budgétaires (non destructif). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exercices', function (Blueprint $table) {
            $table->string('code', 20)->nullable()->after('id');
            $table->boolean('est_actif')->default(false)->after('statut');
            $table->timestamp('date_cloture')->nullable()->after('date_fin');
            $table->foreignId('cloture_par')->nullable()->after('date_cloture')->constrained('users')->nullOnDelete();
        });

        foreach (DB::table('exercices')->get() as $ex) {
            DB::table('exercices')->where('id', $ex->id)->update([
                'code' => $ex->code ?? (string) $ex->annee,
            ]);
        }

        Schema::table('exercices', function (Blueprint $table) {
            $table->unique('code');
        });

        $actifId = DB::table('exercices')->where('statut', 'ouvert')->orderByDesc('annee')->value('id')
            ?? DB::table('exercices')->orderByDesc('annee')->value('id');

        if ($actifId) {
            DB::table('exercices')->update(['est_actif' => false]);
            DB::table('exercices')->where('id', $actifId)->update(['est_actif' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('exercices', function (Blueprint $table) {
            $table->dropForeign(['cloture_par']);
            $table->dropUnique(['code']);
            $table->dropColumn(['code', 'est_actif', 'date_cloture', 'cloture_par']);
        });
    }
};
