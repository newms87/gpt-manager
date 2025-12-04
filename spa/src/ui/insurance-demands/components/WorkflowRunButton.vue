<template>
    <div class="inline-flex">
        <!-- Run Workflow Button -->
        <ActionButton
            type="play"
            :color="color"
            :size="size"
            :tooltip="tooltip"
            :disabled="disabled"
            :loading="isLoading"
            @click="handleClick"
        />

        <!-- Instruction Selector Dialog -->
        <InstructionTemplateSelector
            v-if="showInstructionTemplateSelector"
            :demand="demand"
            @confirm="handleInstructionTemplateSelected"
            @close="showInstructionTemplateSelector = false"
        />

        <!-- Demand Template Selector Dialog -->
        <DemandTemplateSelector
            v-if="showDemandTemplateSelector"
            :demand="demand"
            @confirm="handleDemandTemplateSelected"
            @close="showDemandTemplateSelector = false"
        />
    </div>
</template>

<script setup lang="ts">
import { DemandTemplateSelector, InstructionTemplateSelector } from "@/ui/demand-templates/components";
import { ActionButton } from "quasar-ui-danx";
import { ref } from "vue";
import type { UiDemand, WorkflowConfig } from "../../shared/types";

const props = withDefaults(defineProps<{
    config: WorkflowConfig;
    demand: UiDemand | null;
    disabled?: boolean;
    color?: string;
    size?: string;
    tooltip?: string;
}>(), {
    disabled: false,
    color: "sky",
    size: "xs",
    tooltip: "Run Workflow"
});

const emit = defineEmits<{
    "run": [workflowKey: string, parameters: Record<string, any> | undefined];
}>();

// Loading state
const isLoading = ref(false);

// Dialog state for template/instruction selection
const showInstructionTemplateSelector = ref(false);
const showDemandTemplateSelector = ref(false);

// Handle button click - check if template selection is needed
const handleClick = () => {
    // Check if this workflow needs template selection
    if (props.config.template_categories && props.config.template_categories.length > 0) {
        // Show template selector
        showDemandTemplateSelector.value = true;
    } else if (props.config.instruction_categories && props.config.instruction_categories.length > 0) {
        // Show instruction selector
        showInstructionTemplateSelector.value = true;
    } else {
        // No template needed, emit immediately
        executeWorkflow();
    }
};

// Execute workflow with optional parameters
const executeWorkflow = (parameters?: Record<string, any>) => {
    isLoading.value = true;
    emit("run", props.config.key, parameters);
};

// Handle instruction template selection
const handleInstructionTemplateSelected = (instructionTemplate: any, additionalInstructions: string) => {
    showInstructionTemplateSelector.value = false;

    const parameters: Record<string, any> = {};

    if (instructionTemplate?.id) {
        parameters.instruction_template_id = instructionTemplate.id;
    }

    if (additionalInstructions) {
        parameters.additional_instructions = additionalInstructions;
    }

    executeWorkflow(parameters);
};

// Handle demand template selection
const handleDemandTemplateSelected = (template: any, additionalInstructions: string) => {
    showDemandTemplateSelector.value = false;

    const parameters: Record<string, any> = {
        output_template_id: template.id
    };

    if (additionalInstructions) {
        parameters.additional_instructions = additionalInstructions;
    }

    executeWorkflow(parameters);
};
</script>
