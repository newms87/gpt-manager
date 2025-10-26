<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class GenerateAuthToken extends Command
{
    protected $signature = 'auth:token 
                           {email : User email address}
                           {--team= : Team UUID (optional, defaults to first team)}
                           {--name= : Token name (optional, defaults to team UUID)}';

    protected $description = 'Generate an API authentication token for CLI testing';

    public function handle()
    {
        $email     = $this->argument('email');
        $teamUuid  = $this->option('team');
        $tokenName = $this->option('name');

        $user = User::where('email', $email)->first();
        if (!$user) {
            $this->error("User with email '{$email}' not found.");

            return 1;
        }

        if ($user->roles()->doesntExist()) {
            $this->error("User '{$email}' does not have any roles assigned.");

            return 1;
        }

        $team = null;
        if ($teamUuid) {
            $team = $user->teams()->where('uuid', $teamUuid)->first();
            if (!$team) {
                $this->error("User '{$email}' is not a member of team '{$teamUuid}'.");
                $this->info('Available teams:');
                foreach ($user->teams as $userTeam) {
                    $this->line("  - {$userTeam->name} ({$userTeam->uuid})");
                }

                return 1;
            }
        } else {
            $team = $user->teams()->first();
            if (!$team) {
                $this->error("User '{$email}' is not a member of any teams.");

                return 1;
            }
        }

        $tokenName = $tokenName ?: $team->uuid;
        $token     = $user->createToken($tokenName);

        $this->info('Authentication token generated successfully!');
        $this->newLine();
        $this->line("User: {$user->name} ({$user->email})");
        $this->line("Team: {$team->name} ({$team->uuid})");
        $this->line("Token Name: {$tokenName}");
        $this->newLine();
        $this->line("Token: {$token->plainTextToken}");
        $this->newLine();
        $this->comment('Use this token in CLI requests:');
        $this->line("curl -H 'Authorization: Bearer {$token->plainTextToken}' \\");
        $this->line("     -H 'Accept: application/json' \\");
        $this->line('     http://localhost/api/user');

        return 0;
    }
}
