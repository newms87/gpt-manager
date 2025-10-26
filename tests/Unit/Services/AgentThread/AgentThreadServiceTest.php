<?php

namespace Tests\Unit\Services\AgentThread;

use App\Models\Agent\AgentThread;
use App\Models\Agent\AgentThreadRun;
use App\Services\AgentThread\AgentThreadService;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class AgentThreadServiceTest extends AuthenticatedTestCase
{
    use SetUpTeamTrait;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();
    }

    public function test_withTimeout_setsTimeoutProperty(): void
    {
        // Given
        $service = new AgentThreadService();

        // When
        $result = $service->withTimeout(180);

        // Then
        $this->assertSame($service, $result, 'withTimeout should return the same service instance for chaining');

        // Use reflection to access the protected property
        $reflection      = new \ReflectionClass($service);
        $timeoutProperty = $reflection->getProperty('timeout');
        $timeoutProperty->setAccessible(true);
        $timeout = $timeoutProperty->getValue($service);

        $this->assertEquals(180, $timeout, 'Service timeout property should be set to 180');
    }

    public function test_withTimeout_withNull_setsNullTimeout(): void
    {
        // Given
        $service = new AgentThreadService();

        // When
        $result = $service->withTimeout(null);

        // Then
        $this->assertSame($service, $result, 'withTimeout should return the same service instance for chaining');

        // Use reflection to access the protected property
        $reflection      = new \ReflectionClass($service);
        $timeoutProperty = $reflection->getProperty('timeout');
        $timeoutProperty->setAccessible(true);
        $timeout = $timeoutProperty->getValue($service);

        $this->assertNull($timeout, 'Service timeout property should be null');
    }

    public function test_prepareAgentThreadRun_withTimeout_setsTimeoutInRun(): void
    {
        // Given
        $agentThread = AgentThread::factory()->create();
        $service     = new AgentThreadService();
        $service->withTimeout(240);

        // When
        $agentThreadRun = $service->prepareAgentThreadRun($agentThread);

        // Then
        $this->assertInstanceOf(AgentThreadRun::class, $agentThreadRun, 'Should return AgentThreadRun instance');
        $this->assertEquals(240, $agentThreadRun->timeout, 'AgentThreadRun timeout should match service timeout');
    }

    public function test_prepareAgentThreadRun_withNullTimeout_setsNullInRun(): void
    {
        // Given
        $agentThread = AgentThread::factory()->create();
        $service     = new AgentThreadService();
        $service->withTimeout(null);

        // When
        $agentThreadRun = $service->prepareAgentThreadRun($agentThread);

        // Then
        $this->assertInstanceOf(AgentThreadRun::class, $agentThreadRun, 'Should return AgentThreadRun instance');
        $this->assertNull($agentThreadRun->timeout, 'AgentThreadRun timeout should be null when service timeout is null');
    }

    public function test_prepareAgentThreadRun_withNoTimeoutSet_setsNullInRun(): void
    {
        // Given
        $agentThread = AgentThread::factory()->create();
        $service     = new AgentThreadService();
        // Not calling withTimeout() at all

        // When
        $agentThreadRun = $service->prepareAgentThreadRun($agentThread);

        // Then
        $this->assertInstanceOf(AgentThreadRun::class, $agentThreadRun, 'Should return AgentThreadRun instance');
        $this->assertNull($agentThreadRun->timeout, 'AgentThreadRun timeout should be null when no timeout is set on service');
    }

    public function test_prepareAgentThreadRun_withVariousApiTimeouts_setsCorrectlyInRun(): void
    {
        $testTimeouts = [1, 30, 60, 120, 300, 600, null];

        foreach ($testTimeouts as $timeout) {
            // Given
            $agentThread = AgentThread::factory()->create();
            $service     = new AgentThreadService();
            $service->withTimeout($timeout);

            // When
            $agentThreadRun = $service->prepareAgentThreadRun($agentThread);

            // Then
            $this->assertEquals($timeout, $agentThreadRun->timeout, 'AgentThreadRun API timeout should match service timeout: ' . ($timeout ?? 'null'));

            // Clean up for next iteration
            $agentThreadRun->delete();
        }
    }

    public function test_timeoutFlow_separatesJobAndApiTimeouts(): void
    {
        // Given - API timeout configuration
        $apiTimeout  = 120;
        $agentThread = AgentThread::factory()->create();
        $service     = new AgentThreadService();
        $service->withTimeout($apiTimeout);

        // When - preparing agent thread run
        $agentThreadRun = $service->prepareAgentThreadRun($agentThread);

        // Then - API timeout is stored in AgentThreadRun
        $this->assertEquals($apiTimeout, $agentThreadRun->timeout, 'AgentThreadRun should store API timeout');

        // When - creating job for execution
        $job = new \App\Jobs\ExecuteThreadRunJob($agentThreadRun);

        // Then - job timeout is always 600 seconds, independent of API timeout
        $this->assertEquals(600, $job->timeout, 'Job execution timeout should always be 600 seconds');
        $this->assertNotEquals($apiTimeout, $job->timeout, 'Job timeout should be independent of API timeout');

        // When - creating ResponsesApiOptions with AgentThreadRun timeout
        $apiOptions = \App\Api\Options\ResponsesApiOptions::fromArray([]);
        $apiOptions->setTimeout($agentThreadRun->timeout);

        // Then - API options use the configured timeout for HTTP client
        $this->assertEquals($apiTimeout, $apiOptions->getTimeout(), 'ResponsesApiOptions should use configured timeout for HTTP client');
    }

    public function test_jobTimeout_alwaysRemains600Seconds(): void
    {
        // Given - various timeout configurations
        $testTimeouts = [1, 120, 300, 600, 1000];

        foreach ($testTimeouts as $apiTimeout) {
            // When - creating the job directly with a mock AgentThreadRun
            $agentThreadRun = $this->createMock(\App\Models\Agent\AgentThreadRun::class);
            $job            = new \App\Jobs\ExecuteThreadRunJob($agentThreadRun);

            // Then - job timeout is always 600 seconds regardless of API timeout config
            $this->assertEquals(600, $job->timeout, "Job timeout should always be 600 seconds, not {$apiTimeout}");
        }
    }
}
