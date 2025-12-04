<?php

namespace Tests\Feature\Models;

use App\Models\Demand\DemandTemplate;
use App\Models\Tag;
use App\Models\Workflow\WorkflowInput;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class TagTest extends AuthenticatedTestCase
{
    use RefreshDatabase, SetUpTeamTrait;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();
    }

    #[Test]
    public function creates_tag_with_required_fields(): void
    {
        $tag = Tag::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'medical_writing',
        ]);

        $this->assertDatabaseHas('tags', [
            'id'      => $tag->id,
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'medical_writing',
            'type'    => null,
        ]);
    }

    #[Test]
    public function creates_tag_with_type(): void
    {
        $tag = Tag::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'medical_writing',
            'type'    => 'workflow_category',
        ]);

        $this->assertDatabaseHas('tags', [
            'id'      => $tag->id,
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'medical_writing',
            'type'    => 'workflow_category',
        ]);
    }

    #[Test]
    public function belongs_to_team(): void
    {
        $tag = Tag::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $this->assertInstanceOf(\App\Models\Team\Team::class, $tag->team);
        $this->assertEquals($this->user->currentTeam->id, $tag->team->id);
    }

    #[Test]
    public function scopes_tags_by_team(): void
    {
        $tag1 = Tag::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'tag_team_1',
        ]);

        $otherTeam = \App\Models\Team\Team::factory()->create();
        $tag2      = Tag::factory()->create([
            'team_id' => $otherTeam->id,
            'name'    => 'tag_team_2',
        ]);

        $tags = Tag::forTeam($this->user->currentTeam->id)->get();

        $this->assertCount(1, $tags);
        $this->assertEquals($tag1->id, $tags->first()->id);
    }

    #[Test]
    public function scopes_tags_by_type(): void
    {
        Tag::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'tag1',
            'type'    => 'workflow_category',
        ]);

        Tag::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'tag2',
            'type'    => 'other_type',
        ]);

        Tag::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'tag3',
            'type'    => null,
        ]);

        $workflowTags = Tag::ofType('workflow_category')->get();
        $this->assertCount(1, $workflowTags);
        $this->assertEquals('tag1', $workflowTags->first()->name);

        $nullTypeTags = Tag::ofType(null)->get();
        $this->assertCount(1, $nullTypeTags);
        $this->assertEquals('tag3', $nullTypeTags->first()->name);
    }

    #[Test]
    public function scopes_tags_by_name(): void
    {
        Tag::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'medical_writing',
        ]);

        Tag::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'demand_letter',
        ]);

        $tags = Tag::withName('medical_writing')->get();

        $this->assertCount(1, $tags);
        $this->assertEquals('medical_writing', $tags->first()->name);
    }

    #[Test]
    public function attaches_tag_to_workflow_input(): void
    {
        $workflowInput = WorkflowInput::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $tag = Tag::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'medical_writing',
        ]);

        $workflowInput->attachTag($tag);

        $this->assertTrue($workflowInput->hasTag($tag));
        $this->assertTrue($workflowInput->hasTag('medical_writing'));
        $this->assertCount(1, $workflowInput->tags);
    }

    #[Test]
    public function attaches_tag_by_name_to_workflow_input(): void
    {
        $workflowInput = WorkflowInput::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $workflowInput->attachTag('medical_writing');

        $this->assertTrue($workflowInput->hasTag('medical_writing'));
        $this->assertCount(1, $workflowInput->tags);

        // Verify tag was created with correct team_id
        $tag = Tag::where('name', 'medical_writing')->first();
        $this->assertNotNull($tag);
        $this->assertEquals($this->user->currentTeam->id, $tag->team_id);
    }

    #[Test]
    public function detaches_tag_from_workflow_input(): void
    {
        $workflowInput = WorkflowInput::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $tag = Tag::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'medical_writing',
        ]);

        $workflowInput->attachTag($tag);
        $this->assertTrue($workflowInput->hasTag($tag));

        $workflowInput->detachTag($tag);
        $this->assertFalse($workflowInput->fresh()->hasTag($tag));
    }

    #[Test]
    public function detaches_tag_by_name_from_workflow_input(): void
    {
        $workflowInput = WorkflowInput::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $workflowInput->attachTag('medical_writing');
        $this->assertTrue($workflowInput->hasTag('medical_writing'));

        $workflowInput->detachTag('medical_writing');
        $this->assertFalse($workflowInput->fresh()->hasTag('medical_writing'));
    }

    #[Test]
    public function syncs_tags_on_workflow_input(): void
    {
        $workflowInput = WorkflowInput::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $tag1 = Tag::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'medical_writing',
        ]);
        $tag2 = Tag::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'demand_letter',
        ]);

        // Attach initial tags
        $workflowInput->attachTag($tag1);
        $workflowInput->attachTag($tag2);
        $this->assertCount(2, $workflowInput->fresh()->tags);

        // Sync to just one tag
        $workflowInput->syncTags(['medical_writing']);

        $workflowInput->refresh();
        $this->assertCount(1, $workflowInput->tags);
        $this->assertTrue($workflowInput->hasTag('medical_writing'));
        $this->assertFalse($workflowInput->hasTag('demand_letter'));
    }

    #[Test]
    public function syncs_tags_creates_new_tags_if_needed(): void
    {
        $workflowInput = WorkflowInput::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $workflowInput->syncTags(['new_tag_1', 'new_tag_2']);

        $workflowInput->refresh();
        $this->assertCount(2, $workflowInput->tags);
        $this->assertTrue($workflowInput->hasTag('new_tag_1'));
        $this->assertTrue($workflowInput->hasTag('new_tag_2'));

        // Verify tags exist in database with correct team_id
        $this->assertDatabaseHas('tags', [
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'new_tag_1',
        ]);
        $this->assertDatabaseHas('tags', [
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'new_tag_2',
        ]);
    }

    #[Test]
    public function queries_workflow_inputs_by_tag(): void
    {
        $tag = Tag::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'medical_writing',
        ]);

        $workflowInput1 = WorkflowInput::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Input 1',
        ]);
        $workflowInput1->attachTag($tag);

        $workflowInput2 = WorkflowInput::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Input 2',
        ]);

        $results = WorkflowInput::withTag('medical_writing')->get();

        $this->assertCount(1, $results);
        $this->assertEquals($workflowInput1->id, $results->first()->id);
    }

    #[Test]
    public function queries_workflow_inputs_by_tag_type(): void
    {
        $tag1 = Tag::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'tag1',
            'type'    => 'workflow_category',
        ]);

        $tag2 = Tag::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'tag2',
            'type'    => 'other_type',
        ]);

        $workflowInput1 = WorkflowInput::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);
        $workflowInput1->attachTag($tag1);

        $workflowInput2 = WorkflowInput::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);
        $workflowInput2->attachTag($tag2);

        $results = WorkflowInput::withTagType('workflow_category')->get();

        $this->assertCount(1, $results);
        $this->assertEquals($workflowInput1->id, $results->first()->id);
    }

    #[Test]
    public function attaches_tag_to_demand_template(): void
    {
        $demandTemplate = DemandTemplate::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $tag = Tag::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'demand_letter',
        ]);

        $demandTemplate->attachTag($tag);

        $this->assertTrue($demandTemplate->hasTag($tag));
        $this->assertTrue($demandTemplate->hasTag('demand_letter'));
        $this->assertCount(1, $demandTemplate->tags);
    }

    #[Test]
    public function queries_demand_templates_by_tag(): void
    {
        $tag = Tag::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'demand_letter',
        ]);

        $template1 = DemandTemplate::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
            'name'    => 'Template 1',
        ]);
        $template1->attachTag($tag);

        $template2 = DemandTemplate::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
            'name'    => 'Template 2',
        ]);

        $results = DemandTemplate::withTag('demand_letter')->get();

        $this->assertCount(1, $results);
        $this->assertEquals($template1->id, $results->first()->id);
    }

    #[Test]
    public function tag_relationships_are_polymorphic(): void
    {
        $tag = Tag::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'shared_tag',
        ]);

        $workflowInput = WorkflowInput::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);
        $workflowInput->attachTag($tag);

        $demandTemplate = DemandTemplate::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);
        $demandTemplate->attachTag($tag);

        // Verify tag is attached to both models
        $this->assertCount(1, $tag->workflowInputs);
        $this->assertCount(1, $tag->demandTemplates);
        $this->assertEquals($workflowInput->id, $tag->workflowInputs->first()->id);
        $this->assertEquals($demandTemplate->id, $tag->demandTemplates->first()->id);
    }

    #[Test]
    public function enforces_team_scoping(): void
    {
        $team1 = $this->user->currentTeam;
        $team2 = \App\Models\Team\Team::factory()->create();

        $tag1 = Tag::factory()->create([
            'team_id' => $team1->id,
            'name'    => 'tag1',
        ]);

        $tag2 = Tag::factory()->create([
            'team_id' => $team2->id,
            'name'    => 'tag2',
        ]);

        $workflowInput = WorkflowInput::factory()->create([
            'team_id' => $team1->id,
        ]);

        // Can attach tag from same team
        $workflowInput->attachTag($tag1);
        $this->assertTrue($workflowInput->hasTag($tag1));

        // Can attach tag from different team (polymorphic allows it)
        // But when searching by name, should use team_id scoping
        $workflowInput->attachTag('unique_name');
        $createdTag = Tag::where('name', 'unique_name')->where('team_id', $team1->id)->first();
        $this->assertNotNull($createdTag);
        $this->assertEquals($team1->id, $createdTag->team_id);
    }
}
