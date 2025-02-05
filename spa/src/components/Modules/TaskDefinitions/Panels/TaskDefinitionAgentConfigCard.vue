<template>
	<div>
		<div class="flex items-center">
			<SelectionMenuField
				v-model:editing="isEditingAgent"
				:selected="taskDefinitionAgent.agent"
				selectable
				editable
				creatable
				class="flex-grow"
				:select-icon="AgentIcon"
				select-class="bg-emerald-900 text-cyan-400"
				label-class="text-slate-300"
				:options="dxAgent.pagedItems.value?.data || []"
				:loading="isUpdatingAgent"
				@update:selected="agent => onUpdateAgent({ agent_id: agent.id })"
			/>
			<div class="flex items-center">
				<ActionButton
					type="copy"
					color="sky"
					class="mr-2"
					:saving="copyAgentAction.isApplying"
					@click="copyAgentAction.trigger(taskDefinition, {id: taskDefinitionAgent.id})"
				/>
				<ActionButton
					type="trash"
					color="red"
					:saving="removeAgentAction.isApplying"
					@click="removeAgentAction.trigger(taskDefinition, {id: taskDefinitionAgent.id})"
				/>
			</div>
		</div>

		<div class="mt-4 flex items-center space-x-4">
			<div>
				<QCheckbox
					:model-value="taskDefinitionAgent.include_text"
					label="Include Text?"
					class="text-slate-500"
					@update:model-value="include_text => onUpdateAgent({ include_text })"
				/>
			</div>
			<div>
				<QCheckbox
					:model-value="taskDefinitionAgent.include_files"
					label="Include Files?"
					class="text-slate-500"
					@update:model-value="include_files => onUpdateAgent({ include_files })"
				/>
			</div>
			<div>
				<QCheckbox
					:model-value="taskDefinitionAgent.include_data"
					label="Include Data?"
					class="text-slate-500"
					@update:model-value="include_data => onUpdateAgent({ include_data })"
				/>
			</div>
		</div>

		<Transition>
			<div v-if="taskDefinitionAgent.include_data">
				<div class="mt-4">
					<SchemaEditorToolbox
						can-select
						can-select-fragment
						previewable
						button-color="bg-sky-900 text-sky-200"
						:model-value="taskDefinitionAgent.inputSchema"
						:fragment="taskDefinitionAgent.inputSchemaFragment"
						:loading="isUpdatingInput"
						@update:model-value="schema => onUpdateAgent({ input_schema_id: schema?.id || null, input_schema_fragment_id: null })"
						@update:fragment="fragment => onUpdateAgent({input_schema_fragment_id: fragment?.id || null })"
					>
						<template #header-start>
							<div class="bg-sky-900 text-sky-200 rounded w-20 text-center py-1.5 text-sm mr-4">Input</div>
						</template>
					</SchemaEditorToolbox>
				</div>

				<div class="mt-4">
					<SchemaEditorToolbox
						can-select
						can-select-fragment
						previewable
						button-color="bg-green-900 text-green-200"
						:model-value="taskDefinitionAgent.outputSchema"
						:fragment="taskDefinitionAgent.outputSchemaFragment"
						:loading="isUpdatingOutput"
						@update:model-value="schema => onUpdateAgent({output_schema_id: schema?.id || null, output_schema_fragment_id: null })"
						@update:fragment="fragment => onUpdateAgent({output_schema_fragment_id: fragment?.id || null })"
					>
						<template #header-start>
							<div class="bg-green-900 text-green-200 rounded w-20 text-center py-1.5 text-sm mr-4">Output</div>
						</template>
					</SchemaEditorToolbox>
				</div>
			</div>
		</Transition>
	</div>
</template>
<script setup lang="ts">
import { dxAgent } from "@/components/Modules/Agents";
import SchemaEditorToolbox from "@/components/Modules/SchemaEditor/SchemaEditorToolbox";
import { dxTaskDefinition } from "@/components/Modules/TaskDefinitions";
import { ActionButton } from "@/components/Shared";
import { TaskDefinition, TaskDefinitionAgent } from "@/types";
import { FaSolidRobot as AgentIcon } from "danx-icon";
import { SelectionMenuField } from "quasar-ui-danx";
import { ref } from "vue";

defineEmits(["update", "remove"]);
const props = defineProps<{
	taskDefinition: TaskDefinition;
	taskDefinitionAgent: TaskDefinitionAgent;
}>();

dxAgent.initialize();

const isEditingAgent = ref(false);
const copyAgentAction = dxTaskDefinition.getAction("copy-agent");
const updateAgentAction = dxTaskDefinition.getAction("update-agent");
const removeAgentAction = dxTaskDefinition.getAction("remove-agent");
const isUpdatingAgent = ref(false);
const isUpdatingOutput = ref(false);
const isUpdatingInput = ref(false);

async function onUpdateAgent(data) {
	if (data.agent_id) {
		isUpdatingAgent.value = true;
	} else if (data.output_schema_fragment_id !== undefined) {
		isUpdatingOutput.value = true;
	} else if (data.input_schema_fragment_id !== undefined) {
		isUpdatingInput.value = true;
	}
	await updateAgentAction.trigger(props.taskDefinition, { id: props.taskDefinitionAgent.id, ...data });
	isUpdatingAgent.value = false;
	isUpdatingOutput.value = false;
	isUpdatingInput.value = false;
}
</script>
