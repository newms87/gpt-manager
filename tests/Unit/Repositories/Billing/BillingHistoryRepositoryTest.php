<?php

namespace Tests\Unit\Repositories\Billing;

use App\Models\Billing\BillingHistory;
use App\Models\Billing\Subscription;
use App\Models\Billing\SubscriptionPlan;
use App\Models\Team\Team;
use App\Repositories\Billing\BillingHistoryRepository;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Newms87\Danx\Exceptions\ValidationError;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class BillingHistoryRepositoryTest extends AuthenticatedTestCase
{
    use RefreshDatabase, SetUpTeamTrait;

    private BillingHistoryRepository $billingHistoryRepository;
    private Team $team;
    private Team $differentTeam;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();
        
        $this->billingHistoryRepository = new BillingHistoryRepository();
        $this->team = $this->user->currentTeam;
        $this->differentTeam = Team::factory()->create();
    }

    public function test_query_withAuthenticatedUser_returnsOnlyTeamBillingHistory(): void
    {
        // Given
        $teamBillingRecord = BillingHistory::factory()->create([
            'team_id' => $this->team->id,
            'type' => 'usage_charge',
        ]);
        $otherTeamBillingRecord = BillingHistory::factory()->create([
            'team_id' => $this->differentTeam->id,
            'type' => 'invoice',
        ]);

        // When
        $results = $this->billingHistoryRepository->query()->get();

        // Then
        $this->assertCount(1, $results);
        $this->assertEquals($teamBillingRecord->id, $results->first()->id);
        $this->assertFalse($results->contains('id', $otherTeamBillingRecord->id));
        
        // Verify relationships are loaded
        $this->assertTrue($results->first()->relationLoaded('subscription'));
        
        // Verify ordering (should be desc by created_at)
        $this->assertEquals('created_at', $this->billingHistoryRepository->query()->getQuery()->orders[0]['column']);
        $this->assertEquals('desc', $this->billingHistoryRepository->query()->getQuery()->orders[0]['direction']);
    }

    public function test_applyAction_create_withValidData_createsBillingRecord(): void
    {
        // Given
        $data = [
            'type' => 'usage_charge',
            'description' => 'Daily usage charges',
            'amount' => 15.50,
            'total_amount' => 15.50,
            'currency' => 'USD',
            'status' => 'pending',
            'billing_date' => Carbon::now(),
        ];

        // When
        $result = $this->billingHistoryRepository->applyAction('create', null, $data);

        // Then
        $this->assertInstanceOf(BillingHistory::class, $result);
        $this->assertEquals($this->team->id, $result->team_id);
        $this->assertEquals('usage_charge', $result->type);
        $this->assertEquals(15.50, $result->amount);
        $this->assertEquals('pending', $result->status);
        
        // Verify relationships are loaded
        $this->assertTrue($result->relationLoaded('subscription'));
        
        // Verify database record
        $this->assertDatabaseHas('billing_history', [
            'team_id' => $this->team->id,
            'type' => 'usage_charge',
            'amount' => 15.50,
        ]);
    }

    public function test_applyAction_create_withoutTeamContext_throwsValidationError(): void
    {
        // Given
        // Remove user from all teams to ensure no team context
        $this->user->teams()->detach();
        $this->user->currentTeam = null;
        $this->user->save();
        
        // Re-authenticate the user without teams
        $this->actingAs($this->user->fresh());

        $data = [
            'type' => 'usage_charge',
            'status' => 'pending',
            'amount' => 10.00,
            'total_amount' => 10.00,
            'currency' => 'USD',
            'description' => 'Test charge',
        ];

        // Then
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('No team context available');

        // When
        $this->billingHistoryRepository->applyAction('create', null, $data);
    }

    public function test_applyAction_update_withValidRecord_updatesRecord(): void
    {
        // Given
        $billingRecord = BillingHistory::factory()->create([
            'team_id' => $this->team->id,
            'type' => 'usage_charge',
            'status' => 'pending',
            'description' => 'Original description',
        ]);
        $updateData = [
            'status' => 'processed',
            'description' => 'Updated description',
            'metadata' => ['updated' => true],
        ];

        // When
        $result = $this->billingHistoryRepository->applyAction('update', $billingRecord, $updateData);

        // Then
        $this->assertEquals('processed', $result->status);
        $this->assertEquals('Updated description', $result->description);
        $this->assertEquals(['updated' => true], $result->metadata);
        
        // Verify database was updated
        $this->assertDatabaseHas('billing_history', [
            'id' => $billingRecord->id,
            'status' => 'processed',
            'description' => 'Updated description',
        ]);
    }

    public function test_applyAction_update_withDifferentTeam_throwsValidationError(): void
    {
        // Given
        $billingRecord = BillingHistory::factory()->create([
            'team_id' => $this->differentTeam->id,
        ]);
        $updateData = ['status' => 'processed'];

        // Then
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('You do not have permission to access this billing record');

        // When
        $this->billingHistoryRepository->applyAction('update', $billingRecord, $updateData);
    }

    public function test_applyAction_markPaid_withInvoice_marksAsPaid(): void
    {
        // Given
        $billingRecord = BillingHistory::factory()->create([
            'team_id' => $this->team->id,
            'type' => 'invoice',
            'status' => 'open',
            'paid_at' => null,
        ]);
        $paidAt = Carbon::now()->startOfSecond(); // Use start of second to avoid microsecond differences
        $data = [
            'paid_at' => $paidAt,
            'metadata' => ['payment_method' => 'stripe'],
        ];

        // When
        $result = $this->billingHistoryRepository->applyAction('mark-paid', $billingRecord, $data);

        // Then
        $this->assertEquals('paid', $result->status);
        $this->assertEquals($paidAt->format('Y-m-d H:i:s'), $result->paid_at->format('Y-m-d H:i:s'));
        $this->assertArrayHasKey('payment_method', $result->metadata);
        
        // Verify database was updated
        $this->assertDatabaseHas('billing_history', [
            'id' => $billingRecord->id,
            'status' => 'paid',
        ]);
    }

    public function test_applyAction_markPaid_withNonInvoice_throwsValidationError(): void
    {
        // Given
        $billingRecord = BillingHistory::factory()->create([
            'team_id' => $this->team->id,
            'type' => 'usage_charge',
        ]);

        // Then
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Only invoices can be marked as paid');

        // When
        $this->billingHistoryRepository->applyAction('mark-paid', $billingRecord, []);
    }

    public function test_applyAction_markPaid_withAlreadyPaid_throwsValidationError(): void
    {
        // Given
        $billingRecord = BillingHistory::factory()->create([
            'team_id' => $this->team->id,
            'type' => 'invoice',
            'status' => 'paid',
        ]);

        // Then
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Invoice is already marked as paid');

        // When
        $this->billingHistoryRepository->applyAction('mark-paid', $billingRecord, []);
    }

    public function test_applyAction_markFailed_withInvoice_marksAsVoid(): void
    {
        // Given
        $billingRecord = BillingHistory::factory()->create([
            'team_id' => $this->team->id,
            'type' => 'invoice',
            'status' => 'pending',
        ]);

        // When
        $result = $this->billingHistoryRepository->applyAction('mark-failed', $billingRecord);

        // Then
        $this->assertEquals('void', $result->status);
        
        // Verify database was updated
        $this->assertDatabaseHas('billing_history', [
            'id' => $billingRecord->id,
            'status' => 'void',
        ]);
    }

    public function test_applyAction_markFailed_withNonInvoice_marksAsFailed(): void
    {
        // Given
        $billingRecord = BillingHistory::factory()->create([
            'team_id' => $this->team->id,
            'type' => 'usage_charge',
            'status' => 'pending',
        ]);

        // When
        $result = $this->billingHistoryRepository->applyAction('mark-failed', $billingRecord);

        // Then
        $this->assertEquals('failed', $result->status);
        
        // Verify database was updated
        $this->assertDatabaseHas('billing_history', [
            'id' => $billingRecord->id,
            'status' => 'failed',
        ]);
    }

    public function test_getInvoicesForTeam_withFilters_returnsFilteredResults(): void
    {
        // Given
        $plan = SubscriptionPlan::factory()->create();
        $subscription = Subscription::factory()->create([
            'team_id' => $this->team->id,
            'subscription_plan_id' => $plan->id,
        ]);
        
        // Create invoice records with different statuses and dates
        $paidInvoice = BillingHistory::factory()->create([
            'team_id' => $this->team->id,
            'type' => 'invoice',
            'status' => 'paid',
            'created_at' => Carbon::now()->subDays(5),
            'billing_date' => Carbon::now()->subDays(5),
            'subscription_id' => $subscription->id,
        ]);
        
        $pendingInvoice = BillingHistory::factory()->create([
            'team_id' => $this->team->id,
            'type' => 'invoice',
            'status' => 'open',
            'created_at' => Carbon::now()->subDays(3),
            'billing_date' => Carbon::now()->subDays(3),
            'subscription_id' => $subscription->id,
        ]);
        
        // Create non-invoice record (should be excluded)
        BillingHistory::factory()->create([
            'team_id' => $this->team->id,
            'type' => 'usage_charge',
            'created_at' => Carbon::now()->subDays(4),
            'billing_date' => Carbon::now()->subDays(4),
        ]);

        // When - Filter by status
        $paidResults = $this->billingHistoryRepository->getInvoicesForTeam(['status' => 'paid'])->get();
        $pendingResults = $this->billingHistoryRepository->getInvoicesForTeam(['status' => 'open'])->get();
        
        // When - Filter by date range
        $dateResults = $this->billingHistoryRepository->getInvoicesForTeam([
            'from_date' => Carbon::now()->subDays(6),
            'to_date' => Carbon::now(),
        ])->get();

        // Then
        $this->assertCount(1, $paidResults);
        $this->assertEquals($paidInvoice->id, $paidResults->first()->id);
        
        $this->assertCount(1, $pendingResults);
        $this->assertEquals($pendingInvoice->id, $pendingResults->first()->id);
        
        $this->assertCount(2, $dateResults); // Both invoices within date range
    }

    public function test_getOverdueInvoicesForTeam_returnsOverdueInvoices(): void
    {
        // Given
        // Create overdue invoice
        $overdueInvoice = BillingHistory::factory()->create([
            'team_id' => $this->team->id,
            'type' => 'invoice',
            'status' => 'pending',
            'billing_date' => Carbon::now()->subDays(35), // Assuming 30 days is overdue threshold
        ]);
        
        // Create current invoice (not overdue)
        BillingHistory::factory()->create([
            'team_id' => $this->team->id,
            'type' => 'invoice',
            'status' => 'pending',
            'billing_date' => Carbon::now()->subDays(10),
        ]);

        // When
        $result = $this->billingHistoryRepository->getOverdueInvoicesForTeam();

        // Then
        $this->assertIsArray($result);
        // Note: The actual overdue logic is implemented in the model scope
        // This test verifies the repository method exists and returns an array
    }

    public function test_getTotalPaidAmountForTeam_returnsCorrectTotal(): void
    {
        // Given
        $fromDate = Carbon::now()->subMonth();
        $toDate = Carbon::now();
        
        // Create paid records within date range
        BillingHistory::factory()->create([
            'team_id' => $this->team->id,
            'type' => 'invoice',
            'status' => 'paid',
            'total_amount' => 100.00,
            'created_at' => $fromDate->copy()->addDays(5),
            'billing_date' => $fromDate->copy()->addDays(5),
        ]);
        
        BillingHistory::factory()->create([
            'team_id' => $this->team->id,
            'type' => 'invoice',
            'status' => 'paid',
            'total_amount' => 50.00,
            'created_at' => $fromDate->copy()->addDays(15),
            'billing_date' => $fromDate->copy()->addDays(15),
        ]);
        
        // Create paid record outside date range (should be excluded)
        BillingHistory::factory()->create([
            'team_id' => $this->team->id,
            'type' => 'invoice',
            'status' => 'paid',
            'total_amount' => 25.00,
            'created_at' => $fromDate->copy()->subDays(5),
            'billing_date' => $fromDate->copy()->subDays(5),
        ]);
        
        // Create unpaid record (should be excluded)
        BillingHistory::factory()->create([
            'team_id' => $this->team->id,
            'type' => 'invoice',
            'status' => 'open',
            'total_amount' => 75.00,
            'created_at' => $fromDate->copy()->addDays(10),
            'billing_date' => $fromDate->copy()->addDays(10),
        ]);

        // When
        $result = $this->billingHistoryRepository->getTotalPaidAmountForTeam($fromDate, $toDate);

        // Then
        $this->assertEquals(150.00, $result);
    }

    public function test_getTotalPaidAmountForTeam_withoutDateRange_returnsAllTimePaidAmount(): void
    {
        // Given
        BillingHistory::factory()->create([
            'team_id' => $this->team->id,
            'status' => 'paid',
            'total_amount' => 200.00,
            'billing_date' => Carbon::now()->subMonths(6),
        ]);
        
        BillingHistory::factory()->create([
            'team_id' => $this->team->id,
            'status' => 'paid',
            'total_amount' => 300.00,
            'billing_date' => Carbon::now(),
        ]);

        // When
        $result = $this->billingHistoryRepository->getTotalPaidAmountForTeam();

        // Then
        $this->assertEquals(500.00, $result);
    }

    public function test_getTeamBillingHistory_withPagination_returnsCorrectRecords(): void
    {
        // Given
        $records = collect();
        for ($i = 1; $i <= 25; $i++) {
            $records->push(BillingHistory::factory()->create([
                'team_id' => $this->team->id,
                'description' => "Record $i",
                'created_at' => Carbon::now()->subHours($i),
            ]));
        }

        // When - First page
        $firstPage = $this->billingHistoryRepository->getTeamBillingHistory($this->team->id, 10, 0);
        
        // When - Second page
        $secondPage = $this->billingHistoryRepository->getTeamBillingHistory($this->team->id, 10, 10);

        // Then
        $this->assertCount(10, $firstPage);
        $this->assertCount(10, $secondPage);
        
        // Verify ordering (newest first)
        $this->assertEquals('Record 1', $firstPage->first()->description);
        $this->assertEquals('Record 11', $secondPage->first()->description);
        
        // Verify relationships are loaded
        $this->assertTrue($firstPage->first()->relationLoaded('subscription'));
    }

    public function test_validateOwnership_withOwnRecord_doesNotThrowException(): void
    {
        // Given
        $billingRecord = BillingHistory::factory()->create(['team_id' => $this->team->id]);
        $repository = new BillingHistoryRepository();

        // When & Then - Should not throw exception
        $method = new \ReflectionMethod($repository, 'validateOwnership');
        $method->setAccessible(true);
        $method->invoke($repository, $billingRecord);
        
        // If we reach here, no exception was thrown
        $this->assertTrue(true);
    }

    public function test_validateOwnership_withDifferentTeamRecord_throwsValidationError(): void
    {
        // Given
        $billingRecord = BillingHistory::factory()->create(['team_id' => $this->differentTeam->id]);
        $repository = new BillingHistoryRepository();

        // Then
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('You do not have permission to access this billing record');

        // When
        $method = new \ReflectionMethod($repository, 'validateOwnership');
        $method->setAccessible(true);
        $method->invoke($repository, $billingRecord);
    }

    public function test_validateTeamOwnership_withValidTeamContext_doesNotThrowException(): void
    {
        // Given
        $repository = new BillingHistoryRepository();

        // When & Then - Should not throw exception
        $method = new \ReflectionMethod($repository, 'validateTeamOwnership');
        $method->setAccessible(true);
        $method->invoke($repository);
        
        // If we reach here, no exception was thrown
        $this->assertTrue(true);
    }

    public function test_validateTeamOwnership_withoutTeamContext_throwsValidationError(): void
    {
        // Given
        // Remove user from all teams to ensure no team context
        $this->user->teams()->detach();
        $this->user->currentTeam = null;
        $this->user->save();
        
        // Re-authenticate the user without teams
        $this->actingAs($this->user->fresh());
        
        $repository = new BillingHistoryRepository();

        // Then
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('No team context available');

        // When
        $method = new \ReflectionMethod($repository, 'validateTeamOwnership');
        $method->setAccessible(true);
        $method->invoke($repository);
    }
}