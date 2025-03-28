<template>
	<div>
		<div class="flex items-center">
			<SelectionMenuField
				v-model:editing="isEditingAgent"
				:selected="taskDefinitionAgent.agent"
				selectable
				editable
				deletable
				name-editable
				creatable
				class="flex-grow"
				:select-icon="AgentIcon"
				select-class="bg-emerald-900 text-cyan-400"
				label-class="text-slate-300"
				:options="availableAgents"
				:loading="isUpdatingAgent || isLoadingAgents"
				@create="createAgentAction.trigger(null, {name: taskDefinition.name + ' Agent'})"
				@update:selected="agent => onUpdateDefinitionAgent({ agent_id: agent.id })"
				@update:editing="isTrue => isTrue ? loadAgentDetails(taskDefinitionAgent.agent) : null"
				@update="input => updateAgentAction.trigger(taskDefinitionAgent.agent, input)"
				@delete="agent => deleteAgentAction.trigger(agent)"
			/>
			<div class="flex items-center">
				<ActionButton
					type="copy"
					color="sky"
					class="mr-2"
					:saving="copyDefinitionAgentAction.isApplying"
					@click="copyDefinitionAgentAction.trigger(taskDefinitionAgent)"
				/>
				<ActionButton
					type="trash"
					color="red"
					:saving="removeDefinitionAgentAction.isApplying"
					@click="removeDefinitionAgentAction.trigger(taskDefinitionAgent)"
				/>
			</div>
		</div>

		<div v-if="isEditingAgent" class="mt-4 bg-slate-800 rounded p-8">
			<QSkeleton v-if="taskDefinitionAgent.agent.id === agentToLoadDetails?.id" class="h-16" />
			<template v-else>
				<ActionForm
					:action="updateAgentAction"
					:target="taskDefinitionAgent.agent"
					:form="{fields: agentEditFields}"
				/>
				<AgentDirectiveField :agent="taskDefinitionAgent.agent" />
			</template>
		</div>
		<div class="mt-4 flex items-center space-x-4">
			<div>
				<QCheckbox
					:model-value="taskDefinitionAgent.include_text"
					label="Include Text?"
					class="text-slate-500"
					@update:model-value="include_text => onUpdateDefinitionAgent({ include_text })"
				/>
			</div>
			<div>
				<QCheckbox
					:model-value="taskDefinitionAgent.include_files"
					label="Include Files?"
					class="text-slate-500"
					@update:model-value="include_files => onUpdateDefinitionAgent({ include_files })"
				/>
			</div>
			<div>
				<QCheckbox
					:model-value="taskDefinitionAgent.include_data"
					label="Include Data?"
					class="text-slate-500"
					@update:model-value="include_data => onUpdateDefinitionAgent({ include_data })"
				/>
			</div>
		</div>

		<Transition>
			<div v-if="taskDefinitionAgent.include_data">
				<div
					v-for="(inputSchemaAssociation, index) in taskDefinitionAgent.inputSchemaAssociations"
					:key="inputSchemaAssociation.id"
					class="mt-4 flex items-start flex-nowrap"
				>
					<ActionButton
						type="trash"
						color="white"
						:action="deleteSchemaAssociationAction"
						:target="inputSchemaAssociation"
						class="mr-2"
					/>

					<SchemaEditorToolbox
						can-select
						can-select-fragment
						previewable
						editable
						button-color="bg-sky-900 text-sky-200"
						:model-value="inputSchemaAssociation.schema"
						:fragment="inputSchemaAssociation.fragment"
						:loading="inputSchemaAssociation.isSaving"
						@update:model-value="schema => updateSchemaAssociationAction.trigger(inputSchemaAssociation, { schema_definition_id: schema?.id, schema_fragment_id: null })"
						@update:fragment="(fragment) => updateSchemaAssociationAction.trigger(inputSchemaAssociation, { schema_fragment_id: fragment?.id || null })"
					>
						<template #header-start>
							<div class="bg-sky-900 text-sky-200 rounded w-20 text-center py-1.5 text-sm mr-4">
								Input {{ index + 1 }}
							</div>
						</template>
					</SchemaEditorToolbox>
				</div>
				<div class="mt-4">
					<ActionButton
						type="create"
						color="sky"
						label="Input Schema"
						size="sm"
						class="px-4"
						:action="createSchemaAssociationAction"
						:input="{task_definition_agent_id: taskDefinitionAgent.id, category: 'input'}"
					/>
				</div>
			</div>
		</Transition>
		<div class="mt-4">
			<SchemaEditorToolbox
				can-select
				can-select-fragment
				previewable
				clearable
				editable
				button-color="bg-green-900 text-green-200"
				:model-value="taskDefinitionAgent.outputSchemaAssociation?.schema"
				:fragment="taskDefinitionAgent.outputSchemaAssociation?.fragment"
				:loading="isSavingOutputSchema || taskDefinitionAgent.outputSchemaAssociation?.isSaving"
				@update:model-value="onSelectOutputSchema"
				@update:fragment="(fragment) => updateSchemaAssociationAction.trigger(taskDefinitionAgent.outputSchemaAssociation, { schema_fragment_id: fragment?.id || null })"
			>
				<template #header-start>
					<div class="bg-green-900 text-green-200 rounded w-20 text-center py-1.5 text-sm mr-4">Output</div>
				</template>
			</SchemaEditorToolbox>
		</div>
	</div>
</template>
<script setup lang="ts">
import { dxAgent } from "@/components/Modules/Agents";
import { fields } from "@/components/Modules/Agents/config/fields";
import { AgentDirectiveField } from "@/components/Modules/Agents/Fields";
import SchemaEditorToolbox from "@/components/Modules/SchemaEditor/SchemaEditorToolbox";
import { dxSchemaAssociation } from "@/components/Modules/Schemas/SchemaAssociations";
import { dxTaskDefinition } from "@/components/Modules/TaskDefinitions";
import { dxTaskDefinitionAgent } from "@/components/Modules/TaskDefinitions/TaskDefinitionAgents/config";
import { TaskDefinition, TaskDefinitionAgent } from "@/types";
import { FaSolidRobot as AgentIcon } from "danx-icon";
import { ActionButton, ActionForm, SelectionMenuField } from "quasar-ui-danx";
import { ref } from "vue";
import { agentToLoadDetails, availableAgents, isLoadingAgents, loadAgentDetails, loadAgents } from "./agentStore";

defineEmits(["update", "remove"]);
const props = defineProps<{
	taskDefinition: TaskDefinition;
	taskDefinitionAgent: TaskDefinitionAgent;
}>();

const agentEditFields = fields.filter((field) => ["temperature", "model"].includes(field.name));
const isEditingAgent = ref(false);
const createAgentAction = dxAgent.getAction("quick-create", { onFinish: loadAgents });
const updateAgentAction = dxAgent.getAction("update");
const deleteAgentAction = dxAgent.getAction("delete", { onFinish: loadAgents });
const copyDefinitionAgentAction = dxTaskDefinitionAgent.getAction("copy", { onFinish: refreshTaskDefinition });
const updateDefinitionAgentAction = dxTaskDefinitionAgent.getAction("update");
const removeDefinitionAgentAction = dxTaskDefinitionAgent.getAction("quick-delete", { optimisticDelete: true });
const createSchemaAssociationAction = dxSchemaAssociation.getAction("quick-create", { onFinish: () => dxTaskDefinition.routes.details(props.taskDefinition) });
const updateSchemaAssociationAction = dxSchemaAssociation.getAction("update");
const deleteSchemaAssociationAction = dxSchemaAssociation.getAction("quick-delete", { onFinish: () => dxTaskDefinition.routes.details(props.taskDefinition) });

const isUpdatingAgent = ref(false);
const isSavingOutputSchema = ref(false);

// Immediately load agents eagerly to prepare to show the agent selection menu
loadAgents();

async function refreshTaskDefinition() {
	await dxTaskDefinition.routes.details(props.taskDefinition);
}

async function onUpdateDefinitionAgent(data) {
	if (data.agent_id) {
		isUpdatingAgent.value = true;
	}
	await updateDefinitionAgentAction.trigger(props.taskDefinitionAgent, data);
	isUpdatingAgent.value = false;
}

async function onSelectOutputSchema(schema) {
	isSavingOutputSchema.value = true;
	if (props.taskDefinitionAgent.outputSchemaAssociation) {
		if (!schema) {
			await deleteSchemaAssociationAction.trigger(props.taskDefinitionAgent.outputSchemaAssociation);
		} else {
			await updateSchemaAssociationAction.trigger(props.taskDefinitionAgent.outputSchemaAssociation, {
				schema_definition_id: schema?.id,
				schema_fragment_id: null
			});
		}
	} else {
		await createSchemaAssociationAction.trigger(null, {
			task_definition_agent_id: props.taskDefinitionAgent.id,
			schema_definition_id: schema?.id,
			category: "output"
		});
	}
	isSavingOutputSchema.value = false;
}
</script>
