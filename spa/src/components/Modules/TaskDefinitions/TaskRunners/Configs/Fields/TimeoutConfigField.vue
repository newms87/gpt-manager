<template>
    <div class="timeout-config">
        <NumberField
            :model-value="timeout"
            label="Timeout (seconds)"
            :min="0"
            :max="600"
            help="Maximum time allowed for task execution (1-600 seconds, default: 60)"
            @update:model-value="onUpdateTimeout"
        />
    </div>
</template>

<script setup lang="ts">
import { dxTaskDefinition } from "@/components/Modules/TaskDefinitions";
import { TaskDefinition } from "@/types";
import { NumberField } from "quasar-ui-danx";
import { ref, watch } from "vue";

const props = defineProps<{
    taskDefinition: TaskDefinition;
}>();

let defaultTimeout = 300;
const timeout = ref(props.taskDefinition.task_runner_config?.timeout || defaultTimeout);

// Watch for changes in the task definition
watch(() => props.taskDefinition.task_runner_config?.timeout, (value) => {
    timeout.value = value || defaultTimeout;
});

const updateTaskDefinitionAction = dxTaskDefinition.getAction("update");

function onUpdateTimeout(value: number) {
    timeout.value = value;
    updateConfig();
}

function updateConfig() {
    const updatedConfig = {
        ...props.taskDefinition.task_runner_config,
        timeout: timeout.value
    };

    updateTaskDefinitionAction.trigger(props.taskDefinition, {
        task_runner_config: updatedConfig
    });
}
</script>
