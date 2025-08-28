<template>
    <div>
        <!-- Extract Data Button -->
        <ActionButton
            type="play"
            color="sky"
            :size="size"
            :loading="loadingStates.extractData"
            :label="extractDataLabel"
            :class="buttonItemClass"
            @click="onExtractData"
        />

        <!-- Write Demand Button -->
        <ActionButton
            type="play"
            color="green"
            :size="size"
            :loading="loadingStates.writeDemand"
            :disabled="!canWriteDemand"
            :label="writeDemandLabel"
            :tooltip="writeDemandTooltip"
            :class="buttonItemClass"
            @click="onWriteDemand"
        />
    </div>
</template>

<script setup lang="ts">
import { ActionButton } from "quasar-ui-danx";
import { computed, ref } from "vue";
import type { UiDemand } from "../../shared/types";

interface LoadingStates {
    extractData: boolean;
    writeDemand: boolean;
}

const props = withDefaults(defineProps<{
    demand: UiDemand;
    size?: "xs" | "sm" | "md" | "lg" | "xl";
    buttonClass?: string;
    buttonItemClass?: string;
    extractDataLabel?: string;
    writeDemandLabel?: string;
    loadingStates: LoadingStates;
}>(), {
    size: "md",
    buttonItemClass: "",
    extractDataLabel: "Extract Data",
    writeDemandLabel: "Write Demand"
});

const emit = defineEmits<{
    "extract-data": [];
    "write-demand": [];
}>();

// Computed loading states that combine local loading with demand running states
const loadingStates = computed(() => ({
    extractData: props.loadingStates.extractData || props.demand.is_extract_data_running || isExtractingData.value,
    writeDemand: props.loadingStates.writeDemand || props.demand.is_write_demand_running || isWritingDemand.value
}));

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

const isExtractingData = ref(false);
const isWritingDemand = ref(false);

function onExtractData() {
    emit("extract-data");
    isExtractingData.value = true;
    setTimeout(() => {
        isExtractingData.value = false;
    }, 3000);
}

function onWriteDemand() {
    emit("write-demand");
    isWritingDemand.value = true;
    setTimeout(() => {
        isWritingDemand.value = false;
    }, 3000);
}

</script>
