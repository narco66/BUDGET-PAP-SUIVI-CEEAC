<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Phase A6 — délégations, intérims et historique des affectations utilisateur. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('delegations')) {
            Schema::create('delegations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('structure_id')->constrained('structures')->cascadeOnDelete();
                $table->foreignId('delegue_par')->constrained('users')->cascadeOnDelete();
                $table->foreignId('delegue_a')->constrained('users')->cascadeOnDelete();
                $table->date('date_debut');
                $table->date('date_fin')->nullable();
                $table->text('motif')->nullable();
                $table->boolean('actif')->default(true);
                $table->timestamps();

                $table->index(['structure_id', 'actif']);
                $table->index(['delegue_a', 'actif']);
            });
        }

        if (! Schema::hasTable('interims')) {
            Schema::create('interims', function (Blueprint $table) {
                $table->id();
                $table->foreignId('structure_id')->constrained('structures')->cascadeOnDelete();
                $table->foreignId('titulaire_user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('interim_user_id')->constrained('users')->cascadeOnDelete();
                $table->date('date_debut');
                $table->date('date_fin')->nullable();
                $table->text('motif')->nullable();
                $table->boolean('actif')->default(true);
                $table->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['structure_id', 'actif']);
                $table->index(['titulaire_user_id', 'actif']);
            });
        }

        if (! Schema::hasTable('historiques_affectations')) {
            Schema::create('historiques_affectations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('structure_id')->nullable()->constrained('structures')->nullOnDelete();
                $table->date('date_debut');
                $table->date('date_fin')->nullable();
                $table->boolean('principal')->default(false);
                $table->foreignId('modifie_par')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['user_id', 'date_fin']);
            });
        }

        if (Schema::hasTable('structures') && ! collect(Schema::getIndexes('structures'))->pluck('name')->contains('structures_type_parent_id_actif_index')) {
            Schema::table('structures', function (Blueprint $table) {
                $table->index(['type', 'parent_id', 'actif'], 'structures_type_parent_id_actif_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('historiques_affectations');
        Schema::dropIfExists('interims');
        Schema::dropIfExists('delegations');

        if (Schema::hasTable('structures') && collect(Schema::getIndexes('structures'))->pluck('name')->contains('structures_type_parent_id_actif_index')) {
            Schema::table('structures', function (Blueprint $table) {
                $table->dropIndex('structures_type_parent_id_actif_index');
            });
        }
    }
};
