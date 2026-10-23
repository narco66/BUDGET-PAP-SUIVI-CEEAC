<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Phase P2 — champs enveloppe PAP (référence, responsable, structure, validation). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('paps', function (Blueprint $table) {
            $table->string('reference', 40)->nullable()->after('exercice_id');
            $table->foreignId('structure_id')->nullable()->after('libelle')->constrained('structures')->nullOnDelete();
            $table->foreignId('responsable_id')->nullable()->after('structure_id')->constrained('users')->nullOnDelete();
            $table->date('date_elaboration')->nullable()->after('responsable_id');
            $table->text('observations')->nullable()->after('date_elaboration');
            $table->text('motif_rejet')->nullable()->after('observations');
            $table->timestamp('validated_at')->nullable()->after('publie_le');
            $table->foreignId('validated_by')->nullable()->after('validated_at')->constrained('users')->nullOnDelete();

            $table->unique('reference');
            $table->index('structure_id');
        });

        \Illuminate\Support\Facades\DB::table('paps')
            ->join('exercices', 'exercices.id', '=', 'paps.exercice_id')
            ->whereNull('paps.reference')
            ->select('paps.id', 'exercices.annee')
            ->orderBy('paps.id')
            ->get()
            ->each(function ($row) {
                \Illuminate\Support\Facades\DB::table('paps')->where('id', $row->id)->update([
                    'reference' => 'PAP-'.$row->annee,
                    'date_elaboration' => now()->toDateString(),
                ]);
            });
    }

    public function down(): void
    {
        Schema::table('paps', function (Blueprint $table) {
            $table->dropUnique(['reference']);
            $table->dropIndex(['structure_id']);
            $table->dropConstrainedForeignId('validated_by');
            $table->dropColumn(['validated_at', 'motif_rejet', 'observations', 'date_elaboration']);
            $table->dropConstrainedForeignId('responsable_id');
            $table->dropConstrainedForeignId('structure_id');
            $table->dropColumn('reference');
        });
    }
};
