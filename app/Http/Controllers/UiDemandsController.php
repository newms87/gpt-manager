<?php

namespace App\Http\Controllers;

use App\Models\Demand\UiDemand;
use App\Repositories\UiDemandRepository;
use App\Resources\UiDemandResource;
use App\Services\UiDemand\UiDemandWorkflowService;
use Newms87\Danx\Http\Controllers\ActionController;

class UiDemandsController extends ActionController
{
    public static ?string $repo     = UiDemandRepository::class;

    public static ?string $resource = UiDemandResource::class;

    /**
     * Handle workflow errors consistently
     */
    private function handleWorkflowError(string $action, \Exception $e)
    {
        return response()->json([
            'message' => "Failed to start {$action} workflow.",
            'error'   => $e->getMessage(),
        ], 400);
    }

    public function extractData(UiDemand $uiDemand)
    {
        try {
            app(UiDemandWorkflowService::class)->extractData($uiDemand);

            return UiDemandResource::details($uiDemand);
        } catch (\Exception $e) {
            return $this->handleWorkflowError('extract data', $e);
        }
    }

    public function writeMedicalSummary(UiDemand $uiDemand)
    {
        try {
            $instructionTemplateId  = request()->input('instruction_template_id');
            $additionalInstructions = request()->input('additional_instructions');

            app(UiDemandWorkflowService::class)->writeMedicalSummary($uiDemand, $instructionTemplateId, $additionalInstructions);

            return UiDemandResource::details($uiDemand);
        } catch (\Exception $e) {
            return $this->handleWorkflowError('write medical summary', $e);
        }
    }

    public function writeDemandLetter(UiDemand $uiDemand)
    {
        try {
            $templateId             = request()->input('template_id');
            $additionalInstructions = request()->input('additional_instructions');

            app(UiDemandWorkflowService::class)->writeDemandLetter($uiDemand, $templateId, $additionalInstructions);

            return UiDemandResource::details($uiDemand);
        } catch (\Exception $e) {
            return $this->handleWorkflowError('write demand letter', $e);
        }
    }
}
