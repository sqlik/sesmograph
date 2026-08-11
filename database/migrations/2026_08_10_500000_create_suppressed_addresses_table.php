<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppressed_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('topic_id')->constrained()->cascadeOnDelete();
            $table->string('address');
            $table->string('reason', 20);
            $table->unsignedInteger('hits')->default(1);
            $table->string('last_diagnostic', 500)->nullable();
            $table->timestamp('last_event_at');
            $table->timestamps();

            $table->unique(['topic_id', 'address']);
            // The pre-send check (GET /api/v1/suppressed?address=) looks up by address alone.
            $table->index('address');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suppressed_addresses');
    }
};
