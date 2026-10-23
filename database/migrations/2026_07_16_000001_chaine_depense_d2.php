<?php



use Illuminate\Database\Migrations\Migration;

use Illuminate\Database\Schema\Blueprint;

use Illuminate\Support\Facades\Schema;



/** Phase D2 — tâche PAP, priorité, service fait, ordres de paiement, dossier unifié. */

return new class extends Migration

{

    public function up(): void

    {

        Schema::table('expressions_besoin', function (Blueprint $table) {

            $table->foreignId('tache_id')->nullable()->after('activite_id')->constrained('taches')->nullOnDelete();

            $table->enum('priorite', ['haute', 'moyenne', 'basse'])->default('moyenne')->after('montant_estime');

            $table->index(['exercice_id', 'tache_id', 'statut']);

        });



        Schema::table('engagements', function (Blueprint $table) {

            $table->foreignId('tache_id')->nullable()->after('activite_id')->constrained('taches')->nullOnDelete();

            $table->index(['exercice_id', 'tache_id', 'statut']);

        });



        Schema::create('services_faits', function (Blueprint $table) {

            $table->id();

            $table->foreignId('liquidation_id')->constrained('liquidations')->cascadeOnDelete();

            $table->enum('type', ['reception', 'pv', 'attestation', 'constat'])->default('constat');

            $table->string('reference', 100)->nullable();

            $table->date('date_constat');

            $table->text('constatation')->nullable();

            $table->foreignId('responsable_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

        });



        Schema::create('liquidation_pieces', function (Blueprint $table) {

            $table->id();

            $table->foreignId('liquidation_id')->constrained('liquidations')->cascadeOnDelete();

            $table->enum('type', ['facture', 'bon_livraison', 'pv_reception', 'attestation', 'autre'])->default('facture');

            $table->string('reference', 100)->nullable();

            $table->date('date_piece')->nullable();

            $table->foreignId('piece_justificative_id')->nullable()->constrained('pieces_justificatives')->nullOnDelete();

            $table->timestamps();

            $table->index(['liquidation_id', 'type']);

        });



        Schema::create('ordres_paiement', function (Blueprint $table) {

            $table->id();

            $table->foreignId('ordonnancement_id')->constrained('ordonnancements')->cascadeOnDelete();

            $table->string('numero', 40)->unique();

            $table->decimal('montant', 18, 2);

            $table->date('date_ordre');

            $table->enum('statut', ['emis', 'execute', 'annule'])->default('emis');

            $table->foreignId('emis_par')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('emis_le');

            $table->timestamps();

            $table->index(['ordonnancement_id', 'statut']);

        });



        Schema::table('paiements', function (Blueprint $table) {

            $table->foreignId('ordre_paiement_id')->nullable()->after('ordonnancement_id')

                ->constrained('ordres_paiement')->nullOnDelete();

        });

    }



    public function down(): void

    {

        Schema::table('paiements', function (Blueprint $table) {

            $table->dropConstrainedForeignId('ordre_paiement_id');

        });



        Schema::dropIfExists('ordres_paiement');

        Schema::dropIfExists('liquidation_pieces');

        Schema::dropIfExists('services_faits');



        Schema::table('engagements', function (Blueprint $table) {

            $table->dropConstrainedForeignId('tache_id');

        });



        Schema::table('expressions_besoin', function (Blueprint $table) {

            $table->dropConstrainedForeignId('tache_id');

            $table->dropColumn('priorite');

        });

    }

};


