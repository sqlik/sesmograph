<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('topic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('message_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30);
            $table->timestamp('occurred_at');
            $table->json('payload');
            $table->timestamp('created_at')->useCurrent();

            // Dedup: SNS delivers at-least-once, so replays must be no-ops.
            $table->unique(['message_id', 'type', 'occurred_at']);
            $table->index(['topic_id', 'occurred_at']);
            $table->index(['topic_id', 'type', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
