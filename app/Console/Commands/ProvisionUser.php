<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Stands in for the provisioning channel until SCIM exists.
 *
 * The point of this command is that it is a different door from the login
 * route. Okta decides who someone is; something else decides that they belong
 * here. Once SCIM is wired up, Okta drives that second decision too, but
 * through provisioning events rather than as a side effect of a sign-in.
 */
class ProvisionUser extends Command
{
    protected $signature = 'user:provision
                            {email : The address Okta will assert for this person}
                            {--name= : Display name, defaults to the local part of the email}';

    protected $description = 'Grant someone an account ahead of their first Okta sign-in';

    public function handle(): int
    {
        $email = $this->argument('email');
        $name = $this->option('name') ?: str($email)->before('@')->headline()->toString();

        if ($existing = User::firstWhere('email', $email)) {
            $this->warn("{$email} already has an account (id {$existing->id}).");

            if ($existing->okta_id) {
                $this->line("Already linked to Okta subject {$existing->okta_id}.");
            } else {
                $this->line('Not yet linked to an Okta identity. It will link on their first sign-in.');
            }

            return self::SUCCESS;
        }

        // No password. This account can only ever be reached through Okta,
        // which is the whole point of federating identity.
        $user = User::create([
            'name' => $name,
            'email' => $email,
        ]);

        $this->info("Provisioned {$user->name} <{$user->email}> as id {$user->id}.");
        $this->line('They can now sign in through Okta, and the two records will link on first use.');

        return self::SUCCESS;
    }
}
