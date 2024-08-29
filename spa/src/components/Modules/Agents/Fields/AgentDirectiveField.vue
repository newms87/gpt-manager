<template>
	<div>
		<div class="mb-4">Directives</div>

		<h6>Before</h6>
		<ListTransition
			name="fade-down-list"
			data-drop-zone="top-directives-dz"
		>
			<template v-if="isDragging && !topDirectives.length">
				<div class="text-center text-gray-500 border-dashed border border-slate-500 p-4">Drag Directive Here</div>
			</template>
			<template v-else>
				<ListItemDraggable
					v-for="(agentDirective) in topDirectives"
					:key="agentDirective.id"
					:list-items="topDirectives"
					drop-zone="top-directives-dz"
					show-handle
					handle-class="px-2"
					@update:list-items="onListPositionChange($event, 'Top')"
					@drop-zone="onDropZoneChange"
					@dragstart="onDragStart"
					@dragend="onDragEnd"
				>
					<AgentDirectiveCard
						:agent-directive="agentDirective"
						class="my-2"
						:is-removing="removeDirectiveAction.isApplying"
						@remove="removeDirectiveAction.trigger(agent, { id: agentDirective.directive.id })"
					/>
				</ListItemDraggable>
			</template>
		</ListTransition>

		<h6 class="mt-4">After</h6>
		<ListTransition
			name="fade-down-list"
			data-drop-zone="bottom-directives-dz"
		>
			<template v-if="isDragging && !bottomDirectives.length">
				<div class="text-center text-gray-500 border-dashed border border-slate-500 p-4">Drag Directive Here</div>
			</template>
			<template v-else>
				<ListItemDraggable
					v-for="(agentDirective) in bottomDirectives"
					:key="agentDirective.id"
					:list-items="bottomDirectives"
					drop-zone="bottom-directives-dz"
					show-handle
					handle-class="px-2"
					@update:list-items="onListPositionChange($event, 'Bottom')"
					@drop-zone="onDropZoneChange"
					@dragstart="onDragStart"
					@dragend="onDragEnd"
				>
					<AgentDirectiveCard
						:agent-directive="agentDirective"
						class="my-2"
						:is-removing="removeDirectiveAction.isApplying"
						@remove="removeDirectiveAction.trigger(agent, { id: agentDirective.directive.id })"
					/>
				</ListItemDraggable>
			</template>
		</ListTransition>


		<div class="flex items-stretch flex-nowrap mt-4">
			<SelectField
				class="flex-grow"
				:options="availableDirectives"
				:disable="!availableDirectives.length"
				:placeholder="availableDirectives.length ? 'Select Directive' : 'No Directives Available'"
				@update="addAgentDirective"
			/>
			<QBtn class="bg-green-900 ml-4 w-1/5" :loading="createDirectiveAction.isApplying" @click="onCreateDirective">
				Create
			</QBtn>
		</div>
	</div>
</template>
<script setup lang="ts">
import { getAction } from "@/components/Modules/Agents/agentActions";
import { AgentController } from "@/components/Modules/Agents/agentControls";
import AgentDirectiveCard from "@/components/Modules/Agents/Fields/AgentDirectiveCard";
import { getAction as getDirectiveAction } from "@/components/Modules/Prompts/Directives/promptDirectiveActions";
import { Agent } from "@/types/agents";
import { ListItemDraggable, ListTransition, SelectField } from "quasar-ui-danx";
import { computed, ref } from "vue";

const props = defineProps<{
	agent: Agent,
}>();

const saveDirectiveAction = getAction("save-directive");
const updateDirectivesAction = getAction("update-directives");
const removeDirectiveAction = getAction("remove-directive");
const createDirectiveAction = getDirectiveAction("create", { onFinish: AgentController.loadFieldOptions });

const availableDirectives = computed(() => AgentController.getFieldOptions("promptDirectives").filter((directive) => !props.agent.directives?.find((agentDirective) => agentDirective.directive.id === directive.value)));
const topDirectives = computed(() => props.agent.directives?.filter((directive) => directive.section === "Top") || []);
const bottomDirectives = computed(() => props.agent.directives?.filter((directive) => directive.section === "Bottom") || []);
const isDragging = ref(false);
async function onCreateDirective() {
	const { item: directive } = await createDirectiveAction.trigger();

	if (directive) {
		await addAgentDirective(directive.id);
	}
}

async function addAgentDirective(id) {
	await saveDirectiveAction.trigger(props.agent, { id });
}

function onListPositionChange(directives, section) {
	directives = directives.filter((directive) => directive.id);
	const top = section === "Top" ? directives : topDirectives.value;
	const bottom = section === "Bottom" ? directives : bottomDirectives.value;
	const updatedDirectives = [...top, ...bottom].map((directive, index) => ({
		...directive,
		position: index
	}));
	updateDirectivesAction.trigger(props.agent, { directives: updatedDirectives });
}

function onDropZoneChange(event) {
	const section = event.dropZone.dataset.dropZone === "top-directives-dz" ? "Top" : "Bottom";
	const position = section === "Top" ? topDirectives.value.length + 1 : bottomDirectives.value.length + 1;
	let updatedDirectives = [...props.agent.directives];
	let index = updatedDirectives.findIndex((directive) => directive.id === event.item.id);
	updatedDirectives.splice(index, 1);
	updatedDirectives.splice(position, 0, { ...event.item, position, section });
	updateDirectivesAction.trigger(props.agent, {
		directives: updatedDirectives
	});
}

function onDragStart() {
	setTimeout(() => {
		isDragging.value = true;
	}, 200);
}
function onDragEnd() {
	isDragging.value = false;
}
</script>
