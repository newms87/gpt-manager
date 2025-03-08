<template>
	<div class="node-header flex flex-nowrap items-center space-x-2">
		<template v-if="isWorkflowRunning">
			<template v-if="isRunning">
				<div class="flex-grow">
					<DotLottieVue
						class="w-8 h-8 bg-sky-900 rounded-full"
						autoplay
						loop
						src="https://lottie.host/e61ac963-4a56-4667-ab2f-b54431a0548d/RSumZz9y00.lottie"
					/>
				</div>
				<ActionButton
					type="stop"
					:disabled="!isRunning"
					:action="stopAction"
					:target="taskRun"
					color="red"
					tooltip="Stop task"
					class="p-2"
				/>
			</template>
			<ActionButton
				v-else-if="isStopped"
				type="play"
				:action="resumeAction"
				:target="taskRun"
				color="green-invert"
				tooltip="Continue running task"
				class="p-2"
			/>
		</template>

		<template v-else>
			<div class="flex-grow"></div>
			<ActionButton type="edit" color="sky" :disabled="temporary" @click.stop="$emit('edit')" />
			<ActionButton type="trash" color="red" :disabled="temporary" @click.stop="$emit('remove')" />
		</template>
	</div>
</template>

<script setup lang="ts">
import { dxTaskRun } from "@/components/Modules/TaskDefinitions/TaskRuns/config";
import { activeTaskWorkflowRun } from "@/components/Modules/TaskWorkflows/store";
import { TaskRun } from "@/types";
import { DotLottieVue } from "@lottiefiles/dotlottie-vue";
import { ActionButton } from "quasar-ui-danx";
import { computed } from "vue";

defineEmits<{
	edit: void;
	remove: void;
}>();
const props = defineProps<{
	taskRun?: TaskRun;
	temporary?: boolean;
}>();

const isWorkflowRunning = computed(() => ["Running"].includes(activeTaskWorkflowRun.value?.status));

const resumeAction = dxTaskRun.getAction("resume");
const stopAction = dxTaskRun.getAction("stop");
const isStopped = computed(() => props.taskRun?.status === "Stopped" || props.taskRun?.status === "Pending");
const isRunning = computed(() => ["Running"].includes(props.taskRun?.status));
</script>
