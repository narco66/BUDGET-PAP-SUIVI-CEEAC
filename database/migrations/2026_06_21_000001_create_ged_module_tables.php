<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module GED complet — migration additive, non destructive.
 * Tables indépendantes de pieces_justificatives (socle D8 conservé).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ged_categories')) {
            Schema::create('ged_categories', function (Blueprint $table) {
                $table->id();
                $table->string('code', 50)->unique();
                $table->string('libelle');
                $table->text('description')->nullable();
                $table->foreignId('parent_id')->nullable()->constrained('ged_categories')->nullOnDelete();
                $table->unsignedSmallInteger('ordre')->default(0);
                $table->boolean('actif')->default(true);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('ged_types_documents')) {
            Schema::create('ged_types_documents', function (Blueprint $table) {
                $table->id();
                $table->string('code', 50)->unique();
                $table->string('libelle');
                $table->text('description')->nullable();
                $table->foreignId('categorie_id')->nullable()->constrained('ged_categories')->nullOnDelete();
                $table->json('extensions_autorisees')->nullable();
                $table->unsignedInteger('taille_max_mo')->default(10);
                $table->boolean('signature_requise')->default(false);
                $table->boolean('actif')->default(true);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('ged_dossiers')) {
            Schema::create('ged_dossiers', function (Blueprint $table) {
                $table->id();
                $table->string('reference', 100)->unique();
                $table->string('titre');
                $table->text('description')->nullable();
                $table->foreignId('parent_id')->nullable()->constrained('ged_dossiers')->nullOnDelete();
                $table->foreignId('categorie_id')->nullable()->constrained('ged_categories')->nullOnDelete();
                $table->foreignId('structure_id')->nullable()->constrained('structures')->nullOnDelete();
                $table->foreignId('exercice_id')->nullable()->constrained('exercices')->nullOnDelete();
                $table->string('module_source', 50)->nullable();
                $table->string('chemin_codification')->nullable();
                $table->boolean('est_archive')->default(false);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('ged_workflows')) {
            Schema::create('ged_workflows', function (Blueprint $table) {
                $table->id();
                $table->string('code', 50)->unique();
                $table->string('libelle');
                $table->text('description')->nullable();
                $table->foreignId('type_document_id')->nullable()->constrained('ged_types_documents')->nullOnDelete();
                $table->boolean('actif')->default(true);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('ged_workflow_etapes')) {
            Schema::create('ged_workflow_etapes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workflow_id')->constrained('ged_workflows')->cascadeOnDelete();
                $table->unsignedSmallInteger('ordre');
                $table->string('code', 50);
                $table->string('libelle');
                $table->string('type_etape', 30); // depot, controle, validation, approbation, signature, publication, archivage
                $table->foreignId('role_id')->nullable()->constrained('roles')->nullOnDelete();
                $table->foreignId('utilisateur_id')->nullable()->constrained('users')->nullOnDelete();
                $table->unsignedSmallInteger('delai_jours')->nullable();
                $table->boolean('notification')->default(true);
                $table->timestamps();
                $table->unique(['workflow_id', 'ordre']);
            });
        }

        if (! Schema::hasTable('ged_conservation_rules')) {
            Schema::create('ged_conservation_rules', function (Blueprint $table) {
                $table->id();
                $table->foreignId('type_document_id')->nullable()->constrained('ged_types_documents')->nullOnDelete();
                $table->foreignId('categorie_id')->nullable()->constrained('ged_categories')->nullOnDelete();
                $table->unsignedInteger('duree_annees')->nullable();
                $table->string('sort_final', 40)->default('archivage_definitif');
                $table->unsignedSmallInteger('alerte_jours_avant')->default(30);
                $table->boolean('verrouillage')->default(false);
                $table->text('description')->nullable();
                $table->boolean('actif')->default(true);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('ged_documents')) {
            Schema::create('ged_documents', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->string('reference', 120)->unique();
                $table->string('titre');
                $table->text('description')->nullable();
                $table->foreignId('categorie_id')->nullable()->constrained('ged_categories')->nullOnDelete();
                $table->foreignId('type_document_id')->nullable()->constrained('ged_types_documents')->nullOnDelete();
                $table->foreignId('dossier_id')->nullable()->constrained('ged_dossiers')->nullOnDelete();
                $table->string('module_source', 50)->nullable();
                $table->unsignedBigInteger('module_source_id')->nullable();
                $table->nullableMorphs('attachable');
                $table->string('chemin_fichier');
                $table->string('nom_original');
                $table->string('extension', 20)->nullable();
                $table->string('mime_type', 100)->nullable();
                $table->unsignedBigInteger('taille')->default(0);
                $table->string('hash_sha256', 64)->nullable();
                $table->unsignedSmallInteger('version_courante')->default(1);
                $table->string('statut', 30)->default('brouillon');
                $table->string('niveau_confidentialite', 30)->default('interne');
                $table->date('date_document')->nullable();
                $table->timestamp('date_depot')->nullable();
                $table->date('date_expiration')->nullable();
                $table->timestamp('date_archivage')->nullable();
                $table->boolean('est_archive')->default(false);
                $table->boolean('est_verrouille')->default(false);
                $table->boolean('est_signe')->default(false);
                $table->string('signature_status', 30)->default('non_requis');
                $table->text('contenu_ocr')->nullable();
                $table->json('metadonnees_metier')->nullable();
                $table->foreignId('exercice_id')->nullable()->constrained('exercices')->nullOnDelete();
                $table->foreignId('structure_id')->nullable()->constrained('structures')->nullOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('archived_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();
                $table->index(['statut', 'niveau_confidentialite']);
                $table->index(['module_source', 'module_source_id']);
                $table->index('date_depot');
                $table->index('est_archive');
            });
        }

        if (! Schema::hasTable('ged_versions')) {
            Schema::create('ged_versions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('document_id')->constrained('ged_documents')->cascadeOnDelete();
                $table->unsignedSmallInteger('numero');
                $table->string('chemin_fichier');
                $table->string('nom_original');
                $table->string('extension', 20)->nullable();
                $table->string('mime_type', 100)->nullable();
                $table->unsignedBigInteger('taille')->default(0);
                $table->string('hash_sha256', 64)->nullable();
                $table->string('statut', 30)->default('actif');
                $table->text('commentaire')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->unique(['document_id', 'numero']);
            });
        }

        if (! Schema::hasTable('ged_archives')) {
            Schema::create('ged_archives', function (Blueprint $table) {
                $table->id();
                $table->foreignId('document_id')->constrained('ged_documents')->cascadeOnDelete();
                $table->string('motif');
                $table->text('commentaire')->nullable();
                $table->string('type_archivage', 30)->default('manuel'); // manuel, automatique
                $table->timestamp('date_archivage');
                $table->foreignId('archived_by')->nullable()->constrained('users')->nullOnDelete();
                $table->boolean('est_restaure')->default(false);
                $table->timestamp('date_restauration')->nullable();
                $table->foreignId('restored_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('motif_restauration')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('ged_signatures')) {
            Schema::create('ged_signatures', function (Blueprint $table) {
                $table->id();
                $table->foreignId('document_id')->constrained('ged_documents')->cascadeOnDelete();
                $table->foreignId('version_id')->nullable()->constrained('ged_versions')->nullOnDelete();
                $table->foreignId('signataire_id')->constrained('users')->cascadeOnDelete();
                $table->string('statut', 30)->default('en_attente');
                $table->string('hash_document', 64)->nullable();
                $table->text('empreinte_numerique')->nullable();
                $table->text('certificat_interne')->nullable();
                $table->timestamp('date_demande')->nullable();
                $table->timestamp('date_signature')->nullable();
                $table->timestamp('horodatage')->nullable();
                $table->text('motif_rejet')->nullable();
                $table->string('adresse_ip', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('ged_validations')) {
            Schema::create('ged_validations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('document_id')->constrained('ged_documents')->cascadeOnDelete();
                $table->foreignId('workflow_etape_id')->nullable()->constrained('ged_workflow_etapes')->nullOnDelete();
                $table->foreignId('validateur_id')->constrained('users')->cascadeOnDelete();
                $table->string('statut', 30); // en_attente, valide, rejete
                $table->text('commentaire')->nullable();
                $table->text('motif_rejet')->nullable();
                $table->timestamp('date_action')->nullable();
                $table->unsignedSmallInteger('delai_jours')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('ged_metadonnees')) {
            Schema::create('ged_metadonnees', function (Blueprint $table) {
                $table->id();
                $table->foreignId('document_id')->constrained('ged_documents')->cascadeOnDelete();
                $table->string('cle', 100);
                $table->text('valeur')->nullable();
                $table->string('type_valeur', 20)->default('texte');
                $table->timestamps();
                $table->unique(['document_id', 'cle']);
            });
        }

        if (! Schema::hasTable('ged_tags')) {
            Schema::create('ged_tags', function (Blueprint $table) {
                $table->id();
                $table->string('nom', 80)->unique();
                $table->string('couleur', 20)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('ged_document_tags')) {
            Schema::create('ged_document_tags', function (Blueprint $table) {
                $table->id();
                $table->foreignId('document_id')->constrained('ged_documents')->cascadeOnDelete();
                $table->foreignId('tag_id')->constrained('ged_tags')->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['document_id', 'tag_id']);
            });
        }

        if (! Schema::hasTable('ged_partages')) {
            Schema::create('ged_partages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('document_id')->constrained('ged_documents')->cascadeOnDelete();
                $table->foreignId('partage_par')->constrained('users')->cascadeOnDelete();
                $table->foreignId('partage_avec')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('structure_id')->nullable()->constrained('structures')->nullOnDelete();
                $table->foreignId('role_id')->nullable()->constrained('roles')->nullOnDelete();
                $table->boolean('peut_telecharger')->default(true);
                $table->boolean('peut_previsualiser')->default(true);
                $table->timestamp('expire_le')->nullable();
                $table->text('commentaire')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('ged_acces')) {
            Schema::create('ged_acces', function (Blueprint $table) {
                $table->id();
                $table->foreignId('document_id')->constrained('ged_documents')->cascadeOnDelete();
                $table->foreignId('utilisateur_id')->nullable()->constrained('users')->cascadeOnDelete();
                $table->foreignId('role_id')->nullable()->constrained('roles')->cascadeOnDelete();
                $table->foreignId('structure_id')->nullable()->constrained('structures')->cascadeOnDelete();
                $table->boolean('peut_voir')->default(true);
                $table->boolean('peut_telecharger')->default(false);
                $table->boolean('peut_modifier')->default(false);
                $table->boolean('peut_supprimer')->default(false);
                $table->boolean('peut_archiver')->default(false);
                $table->boolean('peut_signer')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('ged_journaux')) {
            Schema::create('ged_journaux', function (Blueprint $table) {
                $table->id();
                $table->foreignId('document_id')->nullable()->constrained('ged_documents')->nullOnDelete();
                $table->foreignId('utilisateur_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('action', 50);
                $table->string('adresse_ip', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->json('ancienne_valeur')->nullable();
                $table->json('nouvelle_valeur')->nullable();
                $table->text('commentaire')->nullable();
                $table->string('module_source', 50)->nullable();
                $table->timestamps();
                $table->index(['document_id', 'action']);
                $table->index('created_at');
            });
        }

        if (! Schema::hasTable('ged_commentaires')) {
            Schema::create('ged_commentaires', function (Blueprint $table) {
                $table->id();
                $table->foreignId('document_id')->constrained('ged_documents')->cascadeOnDelete();
                $table->foreignId('utilisateur_id')->constrained('users')->cascadeOnDelete();
                $table->text('contenu');
                $table->boolean('est_interne')->default(true);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('ged_favoris')) {
            Schema::create('ged_favoris', function (Blueprint $table) {
                $table->id();
                $table->foreignId('document_id')->constrained('ged_documents')->cascadeOnDelete();
                $table->foreignId('utilisateur_id')->constrained('users')->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['document_id', 'utilisateur_id']);
            });
        }

        if (! Schema::hasTable('ged_corbeille')) {
            Schema::create('ged_corbeille', function (Blueprint $table) {
                $table->id();
                $table->foreignId('document_id')->constrained('ged_documents')->cascadeOnDelete();
                $table->foreignId('supprime_par')->constrained('users')->cascadeOnDelete();
                $table->text('motif')->nullable();
                $table->timestamp('date_suppression');
                $table->timestamp('date_expiration_corbeille')->nullable();
                $table->boolean('est_restaure')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('ged_numerisations')) {
            Schema::create('ged_numerisations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('document_id')->nullable()->constrained('ged_documents')->nullOnDelete();
                $table->foreignId('numerise_par')->constrained('users')->cascadeOnDelete();
                $table->string('source', 30)->default('upload'); // upload, scan, import, generation
                $table->string('appareil')->nullable();
                $table->unsignedInteger('dpi')->nullable();
                $table->boolean('ocr_applique')->default(false);
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ged_numerisations');
        Schema::dropIfExists('ged_corbeille');
        Schema::dropIfExists('ged_favoris');
        Schema::dropIfExists('ged_commentaires');
        Schema::dropIfExists('ged_journaux');
        Schema::dropIfExists('ged_acces');
        Schema::dropIfExists('ged_partages');
        Schema::dropIfExists('ged_document_tags');
        Schema::dropIfExists('ged_tags');
        Schema::dropIfExists('ged_metadonnees');
        Schema::dropIfExists('ged_validations');
        Schema::dropIfExists('ged_signatures');
        Schema::dropIfExists('ged_archives');
        Schema::dropIfExists('ged_versions');
        Schema::dropIfExists('ged_documents');
        Schema::dropIfExists('ged_conservation_rules');
        Schema::dropIfExists('ged_workflow_etapes');
        Schema::dropIfExists('ged_workflows');
        Schema::dropIfExists('ged_dossiers');
        Schema::dropIfExists('ged_types_documents');
        Schema::dropIfExists('ged_categories');
    }
};
