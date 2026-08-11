<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

class CreateAdmin extends Command
{
    protected $signature = 'app:create-admin';

    protected $description = 'Create the admin account (single-user instance)';

    public function handle(): int
    {
        if (User::query()->exists()) {
            $this->error('An admin account already exists. Use app:reset-password to change its password.');

            return self::FAILURE;
        }

        $name = text('Name', required: true);
        $email = text('Email', required: true, validate: fn (string $value) => filter_var($value, FILTER_VALIDATE_EMAIL) ? null : 'Enter a valid email address.');
        $pass = password('Password', required: true, validate: fn (string $value) => strlen($value) >= 12 ? null : 'Use at least 12 characters.');

        User::create([
            'name' => $name,
            'email' => $email,
            'password' => $pass,
        ]);

        $this->info("Admin account created for {$email}. Two-factor setup starts at first login.");

        return self::SUCCESS;
    }
}
