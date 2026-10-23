<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('matricule', 30)->nullable()->unique()->after('id');
            $table->foreignId('structure_id')->nullable()->after('email')
                ->constrained('structures')->nullOnDelete();
            $table->foreignId('fonction_id')->nullable()->after('structure_id')
                ->constrained('fonctions')->nullOnDelete();
            $table->string('telephone', 30)->nullable()->after('fonction_id');
            $table->boolean('actif')->default(true)->after('telephone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('structure_id');
            $table->dropConstrainedForeignId('fonction_id');
            $table->dropColumn(['matricule', 'telephone', 'actif']);
        });
    }
};
