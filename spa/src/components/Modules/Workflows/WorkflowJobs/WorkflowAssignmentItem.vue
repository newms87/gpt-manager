<template>
	<div class="py-3">
		<div class="flex items-center flex-nowrap">
			<div class="flex-grow">
				<template v-if="context === 'agent'">
					<div>{{ assignment.workflowJob.workflow.name }}</div>
					<div class="font-bold ml-2">{{ assignment.workflowJob.name }}</div>
				</template>
				<template v-if="context === 'workflow'">
					<div class="font-bold">{{ assignment.agent.name }}</div>
					<div class="ml-2 text-xs">{{ assignment.agent.model }}</div>
				</template>
			</div>
			<ShowHideButton
				v-if="context === 'workflow'"
				v-model="showResponse"
				label="Response Example"
				class="bg-sky-800 mx-3"
			/>
			<ActionButton
				:action="unassignAction"
				:target="assignment"
				type="trash"
			/>
		</div>
		<div v-if="showResponse" class="mt4">
			<MarkdownEditor :model-value="assignment.agent.response_sample" format="yaml" />
		</div>
	</div>
</template>
<script setup lang="ts">
import MarkdownEditor from "@/components/MardownEditor/MarkdownEditor";
import { ShowHideButton } from "@/components/Shared";
import ActionButton from "@/components/Shared/Buttons/ActionButton";
import { WorkflowAssignment } from "@/types/workflows";
import { ResourceAction } from "quasar-ui-danx";
import { ref } from "vue";

defineProps<{
	assignment: WorkflowAssignment;
	unassignAction: ResourceAction;
	context: "workflow" | "agent";
}>();

const showResponse = ref(false);
</script>
