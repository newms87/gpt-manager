<template>
	<div
		class="flex items-center flex-nowrap justify-center"
		:class="mainClass"
	>
		{{ status }}
		<QSpinnerOrbit v-if="status === WORKFLOW_STATUS.RUNNING.value" class="w-6 flex-shrink-0" />
		<RestartIcon
			v-if="canRestart"
			class="w-3 flex-shrink-0 cursor-pointer	ml-2"
			@click="$emit('restart')"
		/>
	</div>
</template>
<script setup lang="ts">
import { WORKFLOW_STATUS } from "@/components/Modules/TaskWorkflows/workflows";
import { FaSolidArrowsRotate as RestartIcon } from "danx-icon";
import { computed } from "vue";

export interface WorkflowStatusProps {
	status: string;
	restart?: boolean;
	inverse?: boolean,
	statusClass?: string | object;
}

defineEmits(["restart"]);
const props = withDefaults(defineProps<WorkflowStatusProps>(), {
	statusClass: "px-4 py-1.5 rounded-xl"
});

const workflowStatus = computed(() => WORKFLOW_STATUS.resolve(props.status));
const mainClass = computed(() => {
	let cls = { [props.inverse ? workflowStatus.value.classAlt : workflowStatus.value.classPrimary]: true };
	if (typeof props.statusClass === "object") {
		cls = { ...cls, ...props.statusClass };
	} else {
		cls[props.statusClass] = true;
	}
	return cls;
});

const canRestart = computed(() => props.restart && [WORKFLOW_STATUS.COMPLETED.value, WORKFLOW_STATUS.FAILED.value].includes(workflowStatus.value.value));
</script>
