<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_aggregates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('topic_id')->constrained()->cascadeOnDelete();
            $table->date('date');

            // One counter per SES event type; rates are derived, not stored.
            $table->unsignedInteger('send_count')->default(0);
            $table->unsignedInteger('delivery_count')->default(0);
            $table->unsignedInteger('bounce_count')->default(0);
            $table->unsignedInteger('complaint_count')->default(0);
            $table->unsignedInteger('open_count')->default(0);
            $table->unsignedInteger('click_count')->default(0);
            $table->unsignedInteger('reject_count')->default(0);
            $table->unsignedInteger('delivery_delay_count')->default(0);
            $table->unsignedInteger('rendering_failure_count')->default(0);
            $table->unsignedInteger('subscription_count')->default(0);

            $table->timestamps();

            $table->unique(['topic_id', 'date']);
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_aggregates');
    }
};
