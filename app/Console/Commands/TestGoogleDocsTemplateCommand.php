<?php

namespace App\Console\Commands;

use App\Models\Agent\Agent;
use App\Models\Agent\McpServer;
use App\Models\Task\Artifact;
use App\Models\Task\TaskDefinition;
use App\Models\Task\TaskRun;
use App\Models\Team\Team;
use App\Models\User;
use App\Services\Task\Runners\GoogleDocsTemplateTaskRunner;
use Illuminate\Console\Command;

class TestGoogleDocsTemplateCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'test:google-docs-template
                           {--google-doc-id= : Google Doc template ID to use}
                           {--create-test-data : Create test data for demonstration}
                           {--model=gpt-5-mini : AI model to use}
                           {--mcp-server=zapier : MCP server to use for Google Docs access}';

    /**
     * The console command description.
     */
    protected $description = 'Test Google Docs template task runner with MCP integration';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $googleDocId    = $this->option('google-doc-id');
        $createTestData = $this->option('create-test-data');
        $model          = $this->option('model');
        $mcpServerSlug  = $this->option('mcp-server');

        if (!$googleDocId && !$createTestData) {
            $this->error('Please specify either --google-doc-id=ID or --create-test-data');

            return 1;
        }

        $this->info("=== Google Docs Template Task Runner Test ===");
        $this->info("Model: $model");
        $this->info("MCP Server: $mcpServerSlug");
        $this->newLine();

        // Get or create test user and team
        $user = User::first() ?? User::factory()->create();
        $team = $user->teams()->first() ?? Team::factory()->create();

        if (!$user->teams->contains($team)) {
            $team->users()->attach($user);
        }

        // Create or find MCP server
        $mcpServer = McpServer::where('team_id', $team->id)
            ->where('name', 'Zapier_MCP_Server')
            ->first();

        if (!$mcpServer) {
            $this->info("Creating MCP server: Zapier");
            $mcpServer          = new McpServer([
                'name'       => 'Zapier MCP Server',
                'server_url' => 'https://mcp.zapier.com/api/mcp/s/MWI3OTQyYmItYjVmZS00MGZjLWI5NWEtYmI2M2MyNjVmNjJiOjdhYTUxZGZmLTJmZjctNDE0Yi05MjZkLWU2ZmY5ZmYyNWI1Mg==/mcp',
            ]);
            $mcpServer->team_id = $team->id;
            $mcpServer->save();
        }

        // Create agent
        $agent = Agent::factory()->create([
            'team_id' => $team->id,
            'name'    => 'Google Docs Template Agent',
            'model'   => $model,
        ]);

        // Create task definition
        $taskDefinition = TaskDefinition::factory()->create([
            'team_id'            => $team->id,
            'name'               => 'Google Docs Template Test',
            'agent_id'           => $agent->id,
            'task_runner_name'   => GoogleDocsTemplateTaskRunner::RUNNER_NAME,
            'task_runner_config' => [
                'mcp_server_id' => $mcpServer->id,
            ],
        ]);

        // Create task run
        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $taskDefinition->id,
        ]);

        // Create input artifacts
        $artifacts = $createTestData ?
            $this->createTestArtifacts($team, $taskRun) :
            $this->createArtifactsWithDocId($team, $taskRun, $googleDocId);

        $this->info("Created " . count($artifacts) . " input artifacts");
        $this->newLine();

        // Create task process using the proper method
        $taskProcess = $taskRun->taskProcesses()->create([
            'name'   => 'Test Google Docs Template Process',
            'status' => 'pending',
        ]);

        // Attach input artifacts
        $taskProcess->inputArtifacts()->attach($artifacts->pluck('id'));

        $this->info("Running Google Docs Template Task Runner...");

        try {
            $runner = $taskProcess->getRunner();
            $runner->run();

            $this->info("✅ Task completed successfully!");

            // Show output
            $taskProcess->refresh();
            if ($taskProcess->outputArtifacts->count() > 0) {
                $outputArtifact = $taskProcess->outputArtifacts->first();
                $this->newLine();
                $this->info("=== OUTPUT ===");
                if ($outputArtifact->meta && isset($outputArtifact->meta['google_doc_url'])) {
                    $this->info("Google Doc URL: " . $outputArtifact->meta['google_doc_url']);
                }
                if ($outputArtifact->text_content) {
                    $this->info("Response: " . $outputArtifact->text_content);
                }
            }

        } catch(\Exception $e) {
            $this->error("❌ Task failed: " . $e->getMessage());
            $this->error("Stack trace: " . $e->getTraceAsString());

            return 1;
        }

        return 0;
    }

    /**
     * Create test artifacts with sample data
     */
    protected function createTestArtifacts(Team $team, TaskRun $taskRun): \Illuminate\Support\Collection
    {
        // Sample Google Doc template ID
        $googleDocId = '1UzdN0ltymmcSjD964cOxjz8RfyuCakC9z3Y70Dftxoc';

        $artifacts = collect();

        // Artifact with google_doc_file_id in meta and template variables
        $artifact1 = Artifact::create([
            'team_id'      => $team->id,
            'name'         => 'Template Configuration',
            'meta'         => [
                'google_doc_file_id' => $googleDocId,
            ],
            'json_content' => [
                'patient_name'        => 'John Doe',
                'injury_date'         => '2024-01-15',
                'injury_description'  => 'Slip and fall accident at grocery store',
                'medical_provider'    => 'City General Hospital',
                'treatment_summary'   => 'Emergency room visit, X-rays, physical therapy',
                'total_medical_bills' => '$12,500',
                'lost_wages'          => '$3,000',
                'pain_and_suffering'  => 'Severe back pain, limited mobility for 6 weeks',
            ],
        ]);

        // Additional artifact with more variables
        $artifact2 = Artifact::create([
            'team_id'      => $team->id,
            'name'         => 'Additional Information',
            'json_content' => [
                'attorney_name'        => 'Jane Smith, Esq.',
                'attorney_firm'        => 'Smith & Associates',
                'defendant_name'       => 'ABC Grocery Store',
                'incident_location'    => '123 Main Street, Anytown, USA',
                'witnesses'            => 'Mary Johnson, Store Manager',
                'police_report_number' => '2024-12345',
            ],
            'text_content' => "demand_amount: $50,000\nsettle_by_date: 2024-12-31\ncase_number: PI-2024-001",
        ]);

        $artifacts->push($artifact1);
        $artifacts->push($artifact2);

        // Attach to task run
        $taskRun->inputArtifacts()->attach($artifacts->pluck('id'));

        return $artifacts;
    }

    /**
     * Create artifacts with specified Google Doc ID
     */
    protected function createArtifactsWithDocId(Team $team, TaskRun $taskRun, string $googleDocId): \Illuminate\Support\Collection
    {
        $artifacts = collect();

        $artifact = Artifact::create([
            'team_id'      => $team->id,
            'name'         => 'Google Doc Template',
            'json_content' => [
                'google_doc_file_id' => $googleDocId,
                'name'               => 'Test User',
                'date'               => date('Y-m-d'),
                'title'              => 'Test Document',
                'content'            => 'This is test content for the template.',
            ],
        ]);

        $artifacts->push($artifact);

        // Attach to task run
        $taskRun->inputArtifacts()->attach($artifacts->pluck('id'));

        return $artifacts;
    }
}
