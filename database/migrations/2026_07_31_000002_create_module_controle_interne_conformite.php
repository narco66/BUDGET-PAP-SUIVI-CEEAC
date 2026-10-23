<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module Audit, Contrôle Interne & Conformité — Lot C (COSO + conformité).
 * `controles_internes` : dispositif de contrôle cartographié par composante COSO + évaluation d'efficacité.
 * `non_conformites` : écarts détectés + plan de remédiation intégré (action, responsable, échéance, suivi).
 * NON DESTRUCTIF (créations conditionnelles).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('controles_internes')) {
            Schema::create('controles_internes', function (Blueprint $t) {
                $t->id();
                $t->string('code', 40)->unique();
                $t->string('composante', 30);                  // composante COSO
                $t->string('libelle');
                $t->text('description')->nullable();
                $t->string('processus', 150)->nullable();
                $t->foreignId('structure_id')->nullable()->constrained('structures')->nullOnDelete();
                $t->foreignId('responsable_user_id')->nullable()->constrained('users')->nullOnDelete();
                $t->string('type', 20)->default('preventif');  // preventif|detectif|correctif
                $t->string('frequence', 20)->default('permanent');
                $t->string('evaluation', 20)->default('non_evalue'); // efficace|partiel|inefficace|non_evalue
                $t->date('date_evaluation')->nullable();
                $t->boolean('actif')->default(true);
                $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $t->timestamps();
                $t->softDeletes();
                $t->index(['composante', 'evaluation']);
            });
        }

        if (! Schema::hasTable('non_conformites')) {
            Schema::create('non_conformites', function (Blueprint $t) {
                $t->id();
                $t->string('code', 40)->unique();
                $t->foreignId('controle_interne_id')->nullable()->constrained('controles_internes')->nullOnDelete();
                $t->foreignId('mission_audit_id')->nullable()->constrained('missions_audit')->nullOnDelete();
                $t->foreignId('structure_id')->nullable()->constrained('structures')->nullOnDelete();
                $t->foreignId('responsable_user_id')->nullable()->constrained('users')->nullOnDelete();
                $t->string('source', 30)->default('controle_interne'); // controle_interne|audit|reglementaire|autre
                $t->string('libelle');
                $t->text('description')->nullable();
                $t->string('gravite', 20)->default('moyenne');
                $t->string('processus', 150)->nullable();
                $t->date('date_detection')->nullable();
                $t->text('plan_action')->nullable();           // plan de remédiation
                $t->date('echeance')->nullable();
                $t->string('statut', 20)->default('ouverte');  // ouverte|en_traitement|resolue|cloturee
                $t->unsignedTinyInteger('taux_avancement')->default(0);
                $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $t->timestamps();
                $t->softDeletes();
                $t->index(['statut', 'gravite']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('non_conformites');
        Schema::dropIfExists('controles_internes');
    }
};
