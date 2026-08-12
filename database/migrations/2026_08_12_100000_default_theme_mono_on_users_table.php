<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Mono is the default look for new accounts; existing rows
            // keep whatever theme they already stored.
            $table->string('theme', 20)->default('mono')->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('theme', 20)->default('hum')->change();
        });
    }
};
