<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_contents', function (Blueprint $table) {
            $table->id();
            // Content can arrive before the first SES event, so it is keyed
            // by the SES message id and linked to the message lazily.
            $table->string('ses_message_id')->unique();
            $table->foreignId('message_id')->nullable()->constrained()->cascadeOnDelete();
            $table->longText('html_gz')->nullable(); // gzip + base64
            $table->longText('text_gz')->nullable(); // gzip + base64
            $table->unsignedInteger('html_bytes')->default(0);
            $table->timestamp('created_at')->useCurrent();

            $table->index('created_at'); // retention pruning
        });

        Schema::create('api_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('token_hash', 64)->unique();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_contents');
        Schema::dropIfExists('api_tokens');
    }
};
