<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('topics', function (Blueprint $table) {
            // Pinned from the first signed SNS delivery; messages from any
            // other topic ARN are rejected (leaked webhook URL defense).
            $table->string('sns_topic_arn')->nullable()->after('webhook_token');
        });
    }

    public function down(): void
    {
        Schema::table('topics', function (Blueprint $table) {
            $table->dropColumn('sns_topic_arn');
        });
    }
};
