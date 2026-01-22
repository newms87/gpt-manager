<?php

namespace Tests\Unit\Models;

use App\Models\Task\Artifact;
use App\Models\TeamObject\TeamObject;
use PHPUnit\Framework\Attributes\Test;
use Tests\AuthenticatedTestCase;

class TeamObjectArtifactsTest extends AuthenticatedTestCase
{
    #[Test]
    public function it_has_morph_to_many_artifacts_relationship(): void
    {
        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $artifact = Artifact::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $teamObject->artifacts()->attach($artifact->id);

        $this->assertCount(1, $teamObject->artifacts);
        $this->assertEquals($artifact->id, $teamObject->artifacts->first()->id);
    }

    #[Test]
    public function it_attaches_artifacts_with_category_pivot(): void
    {
        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $artifact = Artifact::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $teamObject->artifacts()->attach($artifact->id, ['category' => 'summary']);

        // Verify the pivot table has the category value by using getArtifactsByCategory
        $summaryArtifacts = $teamObject->getArtifactsByCategory('summary');
        $this->assertCount(1, $summaryArtifacts);
        $this->assertEquals($artifact->id, $summaryArtifacts->first()->id);
    }

    #[Test]
    public function it_returns_artifacts_by_category(): void
    {
        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $summaryArtifact = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'name'         => 'Summary Artifact',
            'text_content' => 'This is a summary.',
        ]);

        $analysisArtifact = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'name'         => 'Analysis Artifact',
            'text_content' => 'This is an analysis.',
        ]);

        $anotherSummaryArtifact = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'name'         => 'Another Summary',
            'text_content' => 'More summary content.',
        ]);

        $teamObject->artifacts()->attach($summaryArtifact->id, ['category' => 'summary']);
        $teamObject->artifacts()->attach($analysisArtifact->id, ['category' => 'analysis']);
        $teamObject->artifacts()->attach($anotherSummaryArtifact->id, ['category' => 'summary']);

        $summaryArtifacts = $teamObject->getArtifactsByCategory('summary');
        $analysisArtifacts = $teamObject->getArtifactsByCategory('analysis');

        $this->assertCount(2, $summaryArtifacts);
        $this->assertCount(1, $analysisArtifacts);
        $this->assertTrue($summaryArtifacts->contains($summaryArtifact));
        $this->assertTrue($summaryArtifacts->contains($anotherSummaryArtifact));
        $this->assertTrue($analysisArtifacts->contains($analysisArtifact));
    }

    #[Test]
    public function it_returns_empty_collection_for_nonexistent_category(): void
    {
        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $artifact = Artifact::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $teamObject->artifacts()->attach($artifact->id, ['category' => 'summary']);

        $nonExistentArtifacts = $teamObject->getArtifactsByCategory('nonexistent');

        $this->assertEmpty($nonExistentArtifacts);
    }

    #[Test]
    public function it_orders_artifacts_by_position(): void
    {
        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $artifact1 = Artifact::factory()->create([
            'team_id'  => $this->user->currentTeam->id,
            'name'     => 'Third',
            'position' => 3,
        ]);

        $artifact2 = Artifact::factory()->create([
            'team_id'  => $this->user->currentTeam->id,
            'name'     => 'First',
            'position' => 1,
        ]);

        $artifact3 = Artifact::factory()->create([
            'team_id'  => $this->user->currentTeam->id,
            'name'     => 'Second',
            'position' => 2,
        ]);

        // Attach in random order
        $teamObject->artifacts()->attach($artifact1->id);
        $teamObject->artifacts()->attach($artifact2->id);
        $teamObject->artifacts()->attach($artifact3->id);

        $orderedArtifacts = $teamObject->artifacts()->get();

        $this->assertEquals('First', $orderedArtifacts[0]->name);
        $this->assertEquals('Second', $orderedArtifacts[1]->name);
        $this->assertEquals('Third', $orderedArtifacts[2]->name);
    }

    #[Test]
    public function it_can_attach_multiple_artifacts_to_same_team_object(): void
    {
        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $artifacts = Artifact::factory()->count(5)->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        foreach ($artifacts as $artifact) {
            $teamObject->artifacts()->attach($artifact->id);
        }

        $this->assertCount(5, $teamObject->artifacts);
    }

    #[Test]
    public function it_can_attach_same_artifact_to_multiple_team_objects(): void
    {
        $teamObject1 = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Object One',
        ]);

        $teamObject2 = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Object Two',
        ]);

        $artifact = Artifact::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $teamObject1->artifacts()->attach($artifact->id, ['category' => 'shared']);
        $teamObject2->artifacts()->attach($artifact->id, ['category' => 'shared']);

        $this->assertCount(1, $teamObject1->artifacts);
        $this->assertCount(1, $teamObject2->artifacts);
        $this->assertEquals($artifact->id, $teamObject1->artifacts->first()->id);
        $this->assertEquals($artifact->id, $teamObject2->artifacts->first()->id);
    }

    #[Test]
    public function it_records_timestamps_on_pivot_table(): void
    {
        $teamObject = TeamObject::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $artifact = Artifact::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $teamObject->artifacts()->attach($artifact->id);

        $attachedArtifact = $teamObject->artifacts()->first();

        $this->assertNotNull($attachedArtifact->pivot->created_at);
        $this->assertNotNull($attachedArtifact->pivot->updated_at);
    }
}
