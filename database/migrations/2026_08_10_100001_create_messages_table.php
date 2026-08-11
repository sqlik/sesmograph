<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('topic_id')->constrained()->cascadeOnDelete();
            $table->string('ses_message_id');
            $table->string('subject', 998)->nullable();
            $table->string('from_email')->nullable();
            $table->json('recipients')->nullable();
            $table->string('status', 20)->default('sent');
            $table->timestamp('status_at')->nullable();
            $table->timestamp('last_event_at')->nullable();
            $table->timestamps();

            $table->unique(['topic_id', 'ses_message_id']);
            $table->index(['topic_id', 'last_event_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
