import { demandRoutes } from "../config";
import type { UiDemand } from "../../shared/types";

// Store the current demand reference for reloading artifacts
let currentDemand: UiDemand | null = null;

export function useDemandArtifacts() {
    const setDemand = (demand: UiDemand | null) => {
        currentDemand = demand;
    };

    const reloadArtifactSections = async () => {
        if (!currentDemand?.id) return;

        // Reload just the artifact sections for this demand
        await demandRoutes.details({ id: currentDemand.id }, {
            artifact_sections: { artifacts: { text_content: true, json_content: true, meta: true, files: true } }
        });
    };

    return {
        setDemand,
        reloadArtifactSections
    };
}
