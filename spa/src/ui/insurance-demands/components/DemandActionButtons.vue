<template>
    <div>
        <!-- Extract Data Button -->
        <ActionButton
            type="play"
            color="sky"
            :size="size"
            :saving="isExtractingDataComputed"
            :label="extractDataLabel"
            :class="buttonItemClass"
            @click="handleExtractData"
        />

        <!-- Write Medical Summary Button -->
        <ActionButton
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

        <!-- Write Demand Letter Button -->
        <ActionButton
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
import type { UiDemand } from "../../shared/types";
import { useDemands } from "../composables";

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

// Local state
const isExtractingData = ref(false);
const isWritingMedicalSummary = ref(false);
const isWritingDemandLetter = ref(false);
const showInstructionTemplateSelector = ref(false);
const showDemandTemplateSelector = ref(false);

// Computed loading states based on workflow status and local state
const isExtractingDataComputed = computed(() => props.demand?.is_extract_data_running || isExtractingData.value);
const isWritingMedicalSummaryComputed = computed(() => props.demand?.is_write_medical_summary_running || isWritingMedicalSummary.value);
const isWritingDemandLetterComputed = computed(() => props.demand?.is_write_demand_letter_running || isWritingDemandLetter.value);

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
