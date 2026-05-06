<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('news', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('content')->nullable();
            $table->string('url')->unique();          // Уникальный URL новости (защита от дубликатов)
            $table->string('image_url')->nullable();  // Ссылка на изображение
            $table->string('source')->default('ESPN');// Источник новости
            $table->string('league_slug')->nullable()->index(); // Слаг лиги для фильтрации
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('news');
    }
};