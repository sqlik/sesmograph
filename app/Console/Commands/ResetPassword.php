<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\password;

class ResetPassword extends Command
{
    protected $signature = 'app:reset-password {--disable-2fa : Also remove the TOTP secret and recovery codes}';

    protected $description = 'Reset the admin password (and optionally 2FA) from the CLI';

    public function handle(): int
    {
        $user = User::query()->first();

        if ($user === null) {
            $this->error('No admin account exists yet. Run app:create-admin first.');

            return self::FAILURE;
        }

        $pass = password("New password for {$user->email}", required: true, validate: fn (string $value) => strlen($value) >= 12 ? null : 'Use at least 12 characters.');

        $user->forceFill(['password' => $pass]);

        if ($this->option('disable-2fa') && confirm('Remove TOTP secret and recovery codes? Setup will be required at next login.')) {
            $user->forceFill([
                'totp_secret' => null,
                'totp_confirmed_at' => null,
                'totp_timestamp' => null,
                'recovery_codes' => null,
            ]);
        }

        $user->save();

        $this->info('Password updated.');

        return self::SUCCESS;
    }
}
