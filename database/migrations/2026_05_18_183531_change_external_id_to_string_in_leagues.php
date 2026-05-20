<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leagues', function (Blueprint $table) {
            // Меняем тип поля external_id на VARCHAR(50)
            $table->string('external_id', 50)->change();
        });
    }

    public function down(): void
    {
        Schema::table('leagues', function (Blueprint $table) {
            // Возвращаем обратно (если были только числа, это может быть рискованно)
            $table->unsignedBigInteger('external_id')->change();
        });
    }
};