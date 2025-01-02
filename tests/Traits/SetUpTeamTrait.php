<?php

namespace Tests\Traits;

use Tests\AuthenticatedTestCase;

/**
 * @extends AuthenticatedTestCase
 */
trait SetUpTeamTrait
{
    public function setUpTeam($namespace = 'testing')
    {
        $this->user->currentTeam->update(['namespace' => $namespace]);
    }
}
