<template>
    <div>
        <!-- Extract Data Button (Only shown when authorized) -->
        <ActionButton
            v-if="isAuthorized"
            type="play"
            color="sky"
            :size="size"
            :saving="isExtractingDataComputed"
            :label="extractDataLabel"
            :class="buttonItemClass"
            @click="handleExtractData"
        />

        <!-- Write Medical Summary Button (Only shown when authorized) -->
        <ActionButton
            v-if="isAuthorized"
            type="edit"
            color="teal"
            :size="size"
            :saving="isWritingMedicalSummaryComputed"
            :disabled="!canWriteMedicalSummary"
            :label="writeMedicalSummaryLabel"
            :tooltip="writeMedicalSummaryTooltip"
            :class="buttonItemClass"
            @click="showInstructionTemplateSelector = true"
        />

        <!-- Write Demand Letter Button (Only shown when authorized) -->
        <ActionButton
            v-if="isAuthorized"
            type="document"
            color="green"
            :size="size"
            :saving="isWritingDemandLetterComputed"
            :disabled="!canWriteDemandLetter"
            :label="writeDemandLetterLabel"
            :tooltip="writeDemandLetterTooltip"
            :class="buttonItemClass"
            @click="showDemandTemplateSelector = true"
        />

        <!-- Instruction Selector Dialog -->
        <InstructionTemplateSelector
            v-if="showInstructionTemplateSelector"
            :demand="demand"
            @confirm="handleWriteMedicalSummaryWithTemplate"
            @close="showInstructionTemplateSelector = false"
        />

        <!-- Demand Template Selector Dialog -->
        <DemandTemplateSelector
            v-if="showDemandTemplateSelector"
            :demand="demand"
            @confirm="handleWriteDemandLetterWithTemplate"
            @close="showDemandTemplateSelector = false"
        />
    </div>
</template>

<script setup lang="ts">
import { DemandTemplateSelector, InstructionTemplateSelector } from "@/ui/demand-templates/components";
import { ActionButton } from "quasar-ui-danx";
import { computed, ref } from "vue";
import { useGoogleDocsAuth } from "../../shared/composables/useGoogleDocsAuth";
import type { UiDemand } from "../../shared/types";
import { useDemands, isWorkflowActive } from "../composables";

const props = withDefaults(defineProps<{
    demand: UiDemand;
    size?: "xs" | "sm" | "md" | "lg" | "xl";
    buttonClass?: string;
    buttonItemClass?: string;
    extractDataLabel?: string;
    writeMedicalSummaryLabel?: string;
    writeDemandLetterLabel?: string;
}>(), {
    size: "md",
    buttonItemClass: "",
    extractDataLabel: "Extract Data",
    writeMedicalSummaryLabel: "Write Medical Summary",
    writeDemandLetterLabel: "Write Demand Letter"
});

// Import composable for demand actions
const { extractData, writeMedicalSummary, writeDemandLetter } = useDemands();
const { isAuthorized } = useGoogleDocsAuth();

// Local state for optimistic UI during API calls
const isExtractingData = ref(false);
const isWritingMedicalSummary = ref(false);
const isWritingDemandLetter = ref(false);
const showInstructionTemplateSelector = ref(false);
const showDemandTemplateSelector = ref(false);

// Computed loading states based on workflow_run.status (single source of truth)
// Local state is only used for optimistic UI during the API call itself
const isExtractingDataComputed = computed(() => {
	return isWorkflowActive(props.demand?.extract_data_workflow_run) || isExtractingData.value;
});

const isWritingMedicalSummaryComputed = computed(() => {
	return isWorkflowActive(props.demand?.write_medical_summary_workflow_run) || isWritingMedicalSummary.value;
});

const isWritingDemandLetterComputed = computed(() => {
	return isWorkflowActive(props.demand?.write_demand_letter_workflow_run) || isWritingDemandLetter.value;
});

// Use backend-provided capability flags for consistency
const canWriteMedicalSummary = computed(() => {
    return Boolean(props.demand?.can_write_medical_summary);
});

const writeMedicalSummaryTooltip = computed(() => {
    if (!canWriteMedicalSummary.value) {
        const extractDataFailed = props.demand.extract_data_workflow_run?.status === "Failed";
        if (extractDataFailed) {
            return "Extract data failed - please retry extraction before writing medical summary";
        }
        return "Extract data first before writing medical summary";
    }
    return undefined;
});

const canWriteDemandLetter = computed(() => {
    return Boolean(props.demand?.can_write_demand_letter);
});

const writeDemandLetterTooltip = computed(() => {
    if (!canWriteDemandLetter.value) {
        const medicalSummaryFailed = props.demand.write_medical_summary_workflow_run?.status === "Failed";
        if (medicalSummaryFailed) {
            return "Medical summary failed - please retry before writing demand letter";
        }
        return "Write medical summary first before writing demand letter";
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

const handleWriteMedicalSummaryWithTemplate = async (instructionTemplate: any, instructions: string) => {
    try {
        isWritingMedicalSummary.value = true;
        showInstructionTemplateSelector.value = false; // Close the modal
        await writeMedicalSummary(props.demand, instructionTemplate?.id, instructions);
    } finally {
        isWritingMedicalSummary.value = false;
    }
};

const handleWriteDemandLetterWithTemplate = async (template: any, instructions: string) => {
    try {
        isWritingDemandLetter.value = true;
        showDemandTemplateSelector.value = false; // Close the modal
        await writeDemandLetter(props.demand, template.id, instructions);
    } finally {
        isWritingDemandLetter.value = false;
    }
};

</script>
