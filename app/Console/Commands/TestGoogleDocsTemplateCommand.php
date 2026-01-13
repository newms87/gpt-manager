<?php

namespace App\Console\Commands;

use App\Api\GoogleDocs\GoogleDocsApi;
use App\Models\Agent\Agent;
use App\Models\Task\Artifact;
use App\Models\Task\TaskDefinition;
use App\Models\Task\TaskRun;
use App\Models\TeamObject\TeamObject;
use App\Services\Task\Runners\TemplateTaskRunner;
use Illuminate\Console\Command;
use Newms87\Danx\Models\Utilities\StoredFile;

class TestGoogleDocsTemplateCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'test:google-docs-template
                           {team-id : Team ID for OAuth authentication}
                           {google-doc-id : Google Doc template ID to use}
                           {team-object-id : TeamObject ID to use for template data}
                           {--model=gpt-5-mini : AI model to use}
                           {--auto-accept : Auto-accept all confirmations for testing}';

    /**
     * The console command description.
     */
    protected $description = 'Test Google Docs template task runner - extracts variables and populates with team data';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $teamId       = $this->argument('team-id');
        $googleDocId  = $this->argument('google-doc-id');
        $teamObjectId = $this->argument('team-object-id');
        $model        = $this->option('model');

        $this->info('=== Google Docs Template Task Runner ===');
        $this->info("Team ID: $teamId");
        $this->info("Google Doc ID: $googleDocId");
        $this->info("TeamObject ID: $teamObjectId");
        $this->info("Model: $model");
        $this->newLine();

        // Set the team context using the provided team ID
        // (Team and TeamObject are unrelated - Team is for auth/organization, TeamObject is data)
        $team = \App\Models\Team\Team::find($teamId);
        if (!$team) {
            $this->error("Team with ID $teamId not found");

            return 1;
        }

        // Set authentication context for OAuth to work
        $user = \App\Models\User::first(); // Get any user for console commands
        if ($user) {
            auth()->guard()->setUser($user);
        }

        // Set team context for the current session
        app()->instance('team', $team);

        // Check OAuth status
        $this->info('=== Authentication Status ===');
        $oauthService   = app(\App\Services\Auth\OAuthService::class);
        $requiredScopes = [
            'https://www.googleapis.com/auth/documents',
            'https://www.googleapis.com/auth/drive',
        ];

        if ($oauthService->hasValidTokenWithScopes('google', $requiredScopes, $team)) {
            $this->info("✅ OAuth token available with required scopes for team: {$team->name}");
        } else {
            $hasToken = $oauthService->hasValidToken('google', $team);
            if ($hasToken) {
                $this->warn('❌ OAuth token exists but lacks required scopes - Google Docs template copying requires full drive access');
                $this->info('Current token may only have drive.file scope, but we need drive scope to copy existing documents');
            } else {
                $this->warn('❌ No valid OAuth token available - Google Docs API requires OAuth');
            }
            $this->newLine();

            if (!$oauthService->isConfigured('google')) {
                $this->error('Google OAuth is not configured!');
                $this->info('Please add GOOGLE_OAUTH_CLIENT_ID and GOOGLE_OAUTH_CLIENT_SECRET to .env');
                $this->info('Get credentials from: https://console.cloud.google.com/apis/credentials');

                return 1;
            }

            $promptMessage = $hasToken
                ? 'Would you like to re-authorize Google Docs with the required scopes now?'
                : 'Would you like to authorize Google Docs access now?';

            if (!$this->option('auto-accept') && !$this->confirm($promptMessage)) {
                $this->info('Operation cancelled.');

                return 0;
            }

            try {
                $authUrl = $oauthService->getAuthorizationUrl('google', null, $team);

                $this->info('🔗 Please visit this URL to authorize Google Docs access:');
                $this->newLine();
                $this->line($authUrl);
                $this->newLine();

                $this->info('Instructions:');
                $this->info('1. Copy the URL above');
                $this->info('2. Open it in your browser');
                $this->info('3. Sign in with Google and authorize the application');
                $this->info('4. After authorization, run this command again');
                $this->newLine();

                return 0;

            } catch (\Exception $e) {
                $this->error('Failed to generate authorization URL: ' . $e->getMessage());

                return 1;
            }
        }
        $this->newLine();

        // Load and validate team object
        $teamObject = TeamObject::find($teamObjectId);
        if (!$teamObject) {
            $this->error("TeamObject not found: $teamObjectId");

            return 1;
        }

        // Display team object preview
        $this->info('=== TeamObject Preview ===');
        $this->info("Name: {$teamObject->name}");
        $this->info("Type: {$teamObject->type}");
        $this->info("Created: {$teamObject->created_at->format('Y-m-d H:i:s')}");
        $this->newLine();

        // Extract template variables using the GoogleDocsApi directly
        $this->info('=== Extracting Template Variables ===');
        $this->info('Connecting to Google Docs API to extract template variables...');

        try {
            $googleDocsApi     = app(GoogleDocsApi::class);
            $templateVariables = $googleDocsApi->extractTemplateVariables($googleDocId);
        } catch (\Exception $e) {
            $this->error('Failed to extract template variables: ' . $e->getMessage());

            return 1;
        }

        if (empty($templateVariables)) {
            $this->info('No {{variable}} placeholders found in the template.');
        } else {
            $this->info('=== Template Variables Found ===');
            foreach ($templateVariables as $variable) {
                $this->info("  {{$variable}}");
            }
        }
        $this->newLine();

        // Show available data from TeamObject
        $this->info('=== Available Data from TeamObject ===');
        $availableData = $this->createTeamObjectDataMapping($teamObject);
        foreach ($availableData as $key => $value) {
            $displayValue = is_string($value) ? (strlen($value) > 50 ? substr($value, 0, 50) . '...' : $value) : $value;
            $this->info("  $key: $displayValue");
        }
        $this->newLine();

        // Create team object data mapping
        $teamObjectData = $this->createTeamObjectDataMapping($teamObject);

        // Final confirmation
        if (!$this->option('auto-accept') && !$this->confirm('Proceed to populate the template with this team object data?')) {
            $this->info('Operation cancelled.');

            return 0;
        }

        // Create new agent for document creation
        $documentAgent = Agent::factory()->create([
            'team_id' => $teamObject->team_id ?? 1,
            'name'    => 'Google Docs Template Agent',
            'model'   => $this->option('model'),
        ]);

        // Create task definition for document creation
        $documentTaskDef = TaskDefinition::factory()->create([
            'team_id'            => $teamObject->team_id ?? 1,
            'name'               => 'Google Docs Template Processing',
            'agent_id'           => $documentAgent->id,
            'task_runner_name'   => TemplateTaskRunner::RUNNER_NAME,
            'task_runner_config' => [],
        ]);

        // Create task run
        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $documentTaskDef->id,
        ]);

        // Create StoredFile for the Google Doc template
        $storedFile = StoredFile::firstOrCreate(['filename' => "Google Doc Template: {$googleDocId}"], [
            'disk'     => 'google',
            'filename' => "Google Doc Template: {$googleDocId}",
            'url'      => "https://docs.google.com/document/d/{$googleDocId}/edit",
            'mime'     => 'application/vnd.google-apps.document',
            'size'     => 0,
        ]);

        // Create artifact with team object data
        $artifact = Artifact::create([
            'team_id'      => $teamObject->team_id ?? 1,
            'name'         => 'TeamObject Template Data',
            'meta'         => [
                'template_stored_file_id' => $storedFile->id,
            ],
            'json_content' => $teamObjectData,
        ]);

        // Create task process
        $taskProcess = $taskRun->taskProcesses()->create([
            'name'     => 'Google Docs Template Process',
            'status'   => 'pending',
            'is_ready' => true,
        ]);

        // Attach input artifact
        $taskProcess->inputArtifacts()->attach($artifact->id);

        $this->info('Running Google Docs Template Task Runner...');

        try {
            $runner = $taskProcess->getRunner();
            $runner->run();

            $this->info('✅ Task completed successfully!');

            // Show output
            $taskProcess->refresh();
            if ($taskProcess->outputArtifacts->count() > 0) {
                $outputArtifact = $taskProcess->outputArtifacts->first();
                $this->newLine();
                $this->info('=== OUTPUT ===');
                if ($outputArtifact->meta && isset($outputArtifact->meta['google_doc_url'])) {
                    $this->info('Google Doc URL: ' . $outputArtifact->meta['google_doc_url']);
                }
                if ($outputArtifact->text_content) {
                    $this->info('Response: ' . $outputArtifact->text_content);
                }
            }

        } catch (\Exception $e) {
            $this->error('❌ Task failed: ' . $e->getMessage());
            $this->error('Stack trace: ' . $e->getTraceAsString());

            return 1;
        }

        return 0;
    }

    /**
     * Create team object data mapping for template variables
     */
    protected function createTeamObjectDataMapping(TeamObject $teamObject): array
    {
        return [
            'name'             => $teamObject->name,
            'type'             => $teamObject->type,
            'created_date'     => $teamObject->created_at->format('Y-m-d'),
            'created_datetime' => $teamObject->created_at->format('Y-m-d H:i:s'),
            'team_object_id'   => $teamObject->id,
            'current_date'     => now()->format('Y-m-d'),
            'current_datetime' => now()->format('Y-m-d H:i:s'),
            'current_year'     => now()->format('Y'),
            'current_month'    => now()->format('m'),
            'current_day'      => now()->format('d'),
        ];
    }
}
