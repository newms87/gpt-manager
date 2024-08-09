<?php

namespace App\Teams\TortGuard;

use App\Http\Controllers\Controller;
use App\Models\TeamObject\TeamObject;
use App\Resources\Tortguard\DrugInjuryResource;
use Exception;

class TortguardController extends Controller
{
    /**
     * @return array
     * @throws Exception
     */
    public function getDashboardData(): array
    {
        $drugInjuryObjects = TeamObject::where('type', 'DrugInjury')->get();

        $drugInjuries = [];
        foreach($drugInjuryObjects as $drugInjury) {
            $drugInjuries[] = DrugInjuryResource::details($drugInjury);
        }

        return [
            'drugInjuries' => $drugInjuries,
        ];
    }
}
