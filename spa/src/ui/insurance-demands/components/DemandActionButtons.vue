<template>
    <div>
        <!-- Extract Data Button -->
        <ActionButton
            type="play"
            color="sky"
            :size="size"
            :loading="isExtractingDataComputed"
            :label="extractDataLabel"
            :class="buttonItemClass"
            @click="handleExtractData"
        />

        <!-- Write Demand Button -->
        <ActionButton
            type="play"
            color="green"
            :size="size"
            :loading="isWritingDemandComputed"
            :disabled="!canWriteDemand"
            :label="writeDemandLabel"
            :tooltip="writeDemandTooltip"
            :class="buttonItemClass"
            @click="showTemplateSelector = true"
        />

        <!-- Template Selector Dialog -->
        <DemandTemplateSelector
            v-if="showTemplateSelector"
            @confirm="handleWriteDemandWithTemplate"
            @close="showTemplateSelector = false"
        />
    </div>
</template>

<script setup lang="ts">
import { DemandTemplateSelector } from "@/ui/demand-templates/components";
import { ActionButton } from "quasar-ui-danx";
import { computed, ref } from "vue";
import type { UiDemand } from "../../shared/types";
import { useDemands } from "../composables";

const props = withDefaults(defineProps<{
    demand: UiDemand;
    size?: "xs" | "sm" | "md" | "lg" | "xl";
    buttonClass?: string;
    buttonItemClass?: string;
    extractDataLabel?: string;
    writeDemandLabel?: string;
}>(), {
    size: "md",
    buttonItemClass: "",
    extractDataLabel: "Extract Data",
    writeDemandLabel: "Write Demand"
});

// Import composable for demand actions
const { extractData, writeDemand } = useDemands();

// Local state
const isExtractingData = ref(false);
const isWritingDemand = ref(false);
const showTemplateSelector = ref(false);

// Computed loading states based on workflow status and local state
const isExtractingDataComputed = computed(() => props.demand?.is_extract_data_running || isExtractingData.value);
const isWritingDemandComputed = computed(() => props.demand?.is_write_demand_running || isWritingDemand.value);

// Write Demand button state management
const canWriteDemand = computed(() => {
    // Enable Write Demand if extract data workflow is successfully completed (100% progress AND status "Completed")
    // OR if extract_data_completed_at exists in metadata (for legacy support)
    const workflowCompleted = props.demand.extract_data_workflow_run?.progress_percent === 100 &&
        props.demand.extract_data_workflow_run?.status === "Completed";
    const legacyCompleted = props.demand.metadata?.extract_data_completed_at;

    return Boolean(workflowCompleted || legacyCompleted);
});

const writeDemandTooltip = computed(() => {
    if (!canWriteDemand.value) {
        const extractDataFailed = props.demand.extract_data_workflow_run?.status === "Failed";
        if (extractDataFailed) {
            return "Extract data failed - please retry extraction before writing demand";
        }
        return "Extract data first before writing demand";
    }
    return undefined;
});

// Action handlers
const handleExtractData = async () => {
    try {
        isExtractingData.value = true;
        await extractData(props.demand);
    } finally {
        isExtractingData.value = false;
    }
};

const handleWriteDemandWithTemplate = async (template: any, instructions: string) => {
    try {
        isWritingDemand.value = true;
        showTemplateSelector.value = false; // Close the modal
        await writeDemand(props.demand, template.id, instructions);
    } finally {
        isWritingDemand.value = false;
    }
};

</script>
