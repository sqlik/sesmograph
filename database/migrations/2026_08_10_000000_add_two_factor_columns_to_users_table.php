<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('totp_secret')->nullable()->after('password');
            $table->timestamp('totp_confirmed_at')->nullable()->after('totp_secret');
            // Last accepted TOTP counter, kept to reject replayed codes inside the time window.
            $table->unsignedBigInteger('totp_timestamp')->nullable()->after('totp_confirmed_at');
            $table->text('recovery_codes')->nullable()->after('totp_timestamp');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['totp_secret', 'totp_confirmed_at', 'totp_timestamp', 'recovery_codes']);
        });
    }
};
