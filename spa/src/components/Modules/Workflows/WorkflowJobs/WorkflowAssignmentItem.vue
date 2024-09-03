<template>
	<div class="py-3">
		<div class="flex items-center flex-nowrap">
			<div class="flex-grow">
				<template v-if="context === 'agent'">
					<div>{{ assignment.workflowJob.workflow?.name }}</div>
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
			<ShowHideButton v-model="isEditingAgent" :show-icon="EditIcon" class="mx-2" />
			<ActionButton
				:action="unassignAction"
				:target="assignment"
				type="trash"
			/>
		</div>
		<div v-if="showResponse" class="mt-4">
			<MarkdownEditor
				v-if="agent.responseSchema"
				:model-value="agent.responseSchema.response_example"
				sync-model-changes
				:format="agent.responseSchema.schema_format"
				readonly
			/>
			<template v-else>
				<ActionButton
					:action="generateExampleAction"
					:target="agent.responseSchema"
					:icon="GenerateIcon"
					icon-class="w-6"
					class="bg-lime-900 px-8 mt-4 w-full"
					label="Generate Response Example"
				/>
			</template>
		</div>
		<AgentPanelsDialog v-if="isEditingAgent" :agent="agent" @close="isEditingAgent = false" />
	</div>
</template>
<script setup lang="ts">
import MarkdownEditor from "@/components/MardownEditor/MarkdownEditor";
import AgentPanelsDialog from "@/components/Modules/Agents/AgentPanelsDialog";
import { dxPromptSchema } from "@/components/Modules/Prompts/Schemas";
import ActionButton from "@/components/Shared/Buttons/ActionButton";
import { Agent } from "@/types";
import { WorkflowAssignment } from "@/types/workflows";
import { FaSolidPencil as EditIcon, FaSolidRobot as GenerateIcon } from "danx-icon";
import { ResourceAction, ShowHideButton } from "quasar-ui-danx";
import { computed, ref } from "vue";

const props = defineProps<{
	assignment: WorkflowAssignment;
	unassignAction: ResourceAction;
	context: "workflow" | "agent";
}>();

const showResponse = ref(false);
const isEditingAgent = ref(false);
const generateExampleAction = dxPromptSchema.getAction("generate-example");
const agent = computed<Agent>(() => props.assignment.agent);
</script>
