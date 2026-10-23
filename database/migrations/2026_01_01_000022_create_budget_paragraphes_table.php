<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_paragraphes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained('budget_articles')->cascadeOnDelete();
            $table->string('code', 10);
            $table->string('intitule');
            $table->unsignedInteger('ordre')->default(0);
            $table->timestamps();

            $table->unique(['article_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_paragraphes');
    }
};
