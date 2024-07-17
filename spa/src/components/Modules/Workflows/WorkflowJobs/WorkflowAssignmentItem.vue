<template>
	<div class="py-3">
		<div class="flex items-center flex-nowrap">
			<div class="flex-grow">
				<template v-if="context === 'agent'">
					<div>{{ assignment.workflowJob.workflow.name }}</div>
					<div class="font-bold ml-2">{{ assignment.workflowJob.name }}</div>
				</template>
				<template v-if="context === 'workflow'">
					<div class="font-bold">{{ agent.name }}</div>
					<div class="ml-2 text-xs">{{ agent.model }}</div>
				</template>
			</div>
			<ShowHideButton
				v-if="context === 'workflow'"
				v-model="showResponse"
				label="Response Preview"
				class="bg-sky-800 mx-3"
			/>
			<ActionButton
				:action="unassignAction"
				:target="assignment"
				type="trash"
			/>
		</div>
		<div v-if="showResponse" class="mt4">
			<MarkdownEditor
				v-if="agent.response_sample"
				:model-value="agent.response_sample"
				sync-model-changes
				format="yaml"
				readonly
			/>
			<template v-else>
				<ActionButton
					:action="sampleAction"
					:target="assignment.agent"
					:icon="GenerateIcon"
					icon-class="w-6"
					class="bg-lime-900 px-8 mt-4 w-full"
					label="Generate Response Preview"
				/>
			</template>
		</div>
	</div>
</template>
<script setup lang="ts">
import MarkdownEditor from "@/components/MardownEditor/MarkdownEditor";
import { getAction } from "@/components/Modules/Agents/agentActions";
import { ShowHideButton } from "@/components/Shared";
import ActionButton from "@/components/Shared/Buttons/ActionButton";
import { Agent } from "@/types";
import { WorkflowAssignment } from "@/types/workflows";
import { FaSolidRobot as GenerateIcon } from "danx-icon";
import { ResourceAction } from "quasar-ui-danx";
import { computed, ref } from "vue";

const props = defineProps<{
	assignment: WorkflowAssignment;
	unassignAction: ResourceAction;
	context: "workflow" | "agent";
}>();

const showResponse = ref(false);
const sampleAction = getAction("generate-sample");
const agent = computed<Agent>(() => props.assignment.agent);
</script>
