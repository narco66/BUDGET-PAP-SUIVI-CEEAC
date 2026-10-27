<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** P1 — ajoute le type « impact » aux évaluations S&E (non destructif, cross-DB via change()). */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('evaluations_periodiques')) {
            return;
        }

        $this->redefinirType(['periodique', 'campagne', 'annuelle', 'impact']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('evaluations_periodiques')) {
            return;
        }

        // Reclasse les évaluations d'impact avant de retirer la valeur de l'énum.
        DB::table('evaluations_periodiques')
            ->where('type', 'impact')
            ->update(['type' => 'annuelle']);

        $this->redefinirType(['periodique', 'campagne', 'annuelle']);
    }

    /** PostgreSQL : l'énum est une contrainte CHECK que change() ne sait pas réécrire. */
    private function redefinirType(array $valeurs): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            $liste = implode(',', array_map(fn (string $v) => "'{$v}'", $valeurs));
            DB::statement('ALTER TABLE evaluations_periodiques DROP CONSTRAINT IF EXISTS evaluations_periodiques_type_check');
            DB::statement("ALTER TABLE evaluations_periodiques ADD CONSTRAINT evaluations_periodiques_type_check CHECK (type::text = ANY (ARRAY[{$liste}]::text[]))");

            return;
        }

        Schema::table('evaluations_periodiques', function (Blueprint $table) use ($valeurs) {
            $table->enum('type', $valeurs)
                ->default('periodique')
                ->change();
        });
    }
};
