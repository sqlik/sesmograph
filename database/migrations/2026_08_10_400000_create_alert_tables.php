<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alert_channels', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('type', 20); // smtp | telegram | pushover | webhook
            $table->text('config'); // encrypted json: credentials live here
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });

        Schema::create('alert_rules', function (Blueprint $table) {
            $table->id();
            // Null topic = the rule watches every topic.
            $table->foreignId('topic_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('type', 20); // immediate | threshold
            $table->json('config');
            $table->unsignedSmallInteger('cooldown_minutes')->default(30);
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });

        Schema::create('alert_rule_channel', function (Blueprint $table) {
            $table->foreignId('alert_rule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('alert_channel_id')->constrained()->cascadeOnDelete();
            $table->unique(['alert_rule_id', 'alert_channel_id']);
        });

        Schema::create('alerts_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alert_rule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('topic_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('subject');
            $table->text('body');
            $table->json('context')->nullable();
            $table->json('delivery')->nullable(); // per-channel outcome
            $table->timestamp('created_at')->useCurrent();

            // Cooldown lookups: newest alert for a rule+topic pair.
            $table->index(['alert_rule_id', 'topic_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alerts_log');
        Schema::dropIfExists('alert_rule_channel');
        Schema::dropIfExists('alert_rules');
        Schema::dropIfExists('alert_channels');
    }
};
