<?php

namespace App\Console\Commands;

use App\Models\Team\Team;
use App\Services\Auth\OAuthService;
use Illuminate\Console\Command;

class GoogleOAuthAuthorizeCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'google:oauth:authorize
                           {--team-id=1 : Team ID to authorize for}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get Google OAuth authorization URL for Google Docs API';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $teamId = $this->option('team-id');

        $team = Team::find($teamId);
        if (!$team) {
            $this->error("Team not found: {$teamId}");

            return 1;
        }

        $this->info('=== Google OAuth Authorization ===');
        $this->info("Team: {$team->name} (ID: {$teamId})");
        $this->newLine();

        $oauthService = app(OAuthService::class);

        // Check if OAuth is configured
        if (!$oauthService->isConfigured('google')) {
            $this->error('Google OAuth not configured!');
            $this->error('Please add GOOGLE_OAUTH_CLIENT_ID and GOOGLE_OAUTH_CLIENT_SECRET to .env');
            $this->newLine();
            $this->info('Get credentials from:');
            $this->info('https://console.cloud.google.com/apis/credentials');

            return 1;
        }

        try {
            // Set team context
            app()->instance('team', $team);

            // Check if already has token
            if ($oauthService->hasValidToken('google')) {
                $this->info('✅ Team already has valid Google OAuth token!');
                $this->info('No authorization needed.');

                return 0;
            }

            // Generate authorization URL
            $authUrl = $oauthService->getAuthorizationUrl('google', null, $team);

            $this->info('🔗 Authorization URL generated:');
            $this->newLine();
            $this->line($authUrl);
            $this->newLine();

            $this->info('Instructions:');
            $this->info('1. Copy the URL above');
            $this->info('2. Open it in your browser');
            $this->info('3. Sign in with Google and authorize the application');
            $this->info('4. Complete the OAuth flow');
            $this->newLine();
            $this->info('Note: The OAuth callback will be handled automatically when configured properly.');

        } catch (\Exception $e) {
            $this->error('Failed to generate authorization URL: ' . $e->getMessage());

            return 1;
        }

        return 0;
    }
}
