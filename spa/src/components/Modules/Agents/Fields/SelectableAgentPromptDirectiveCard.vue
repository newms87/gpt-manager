<template>
	<div class="bg-slate-800 rounded-lg px-3 py-1 cursor-default">
		<SelectionMenuField
			v-model:editing="isEditing"
			:selected="promptDirective"
			selectable
			editable
			deletable
			name-editable
			creatable
			clearable
			label-class="text-slate-300"
			size="sm"
			:select-icon="DirectiveIcon"
			:options="listItems"
			:loading="isRefreshing || isRemoving"
			@create="saveAgentDirectiveAction.trigger(agent, {agent_prompt_directive_id: agentDirective.id})"
			@update:selected="onSelect"
			@update="input => updatePromptDirectiveAction.trigger(promptDirective, input)"
			@delete="pd => deletePromptDirectiveAction.trigger(pd)"
		/>
		<div v-if="isEditing" class="mt-2">
			<MarkdownEditor
				:model-value="promptDirective.directive_text"
				:max-length="64000"
				@update:model-value="debouncedUpdatePromptDirectiveAction.trigger(promptDirective, {directive_text: $event})"
			/>
		</div>
	</div>
</template>

<script setup lang="ts">
import MarkdownEditor from "@/components/MarkdownEditor/MarkdownEditor";
import { dxAgent } from "@/components/Modules/Agents";
import { dxPromptDirective } from "@/components/Modules/Prompts/Directives";
import { Agent, AgentPromptDirective, PromptDirective } from "@/types";
import { FaSolidFileLines as DirectiveIcon } from "danx-icon";
import { SelectionMenuField } from "quasar-ui-danx";
import { computed, onMounted, ref } from "vue";

const emit = defineEmits<{
	change: PromptDirective;
	remove: void;
	deleted: void;
}>();
const props = defineProps<{
	agent: Agent;
	agentDirective: AgentPromptDirective;
	isRemoving: boolean;
}>();

const promptDirective = computed(() => props.agentDirective.directive);
const isEditing = ref(false);
const { listItems, isRefreshing, refreshItems, loadItems } = dxPromptDirective.store;

const saveAgentDirectiveAction = dxAgent.getAction("save-directive", { onFinish: refreshItems });
const updatePromptDirectiveAction = dxPromptDirective.getAction("update");
const debouncedUpdatePromptDirectiveAction = dxPromptDirective.getAction("update", { debounce: 500 });
const deletePromptDirectiveAction = dxPromptDirective.getAction("delete", {
	onFinish: async () => {
		await refreshItems();
		await dxPromptDirective.routes.details(promptDirective.value);
		emit("deleted");
	}
});

onMounted(loadItems);

function onSelect(newPromptDirective: PromptDirective) {
	if (newPromptDirective) {
		saveAgentDirectiveAction.trigger(props.agent, {
			agent_prompt_directive_id: props.agentDirective.id,
			prompt_directive_id: newPromptDirective.id
		});
	} else {
		emit("remove");
	}
}
</script>
