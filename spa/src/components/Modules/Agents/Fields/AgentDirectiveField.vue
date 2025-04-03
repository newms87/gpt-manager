<template>
	<div>
		<h5>Directives</h5>
		<div class="bg-slate-900 p-4 rounded mt-4 space-y-4">
			<ActionButton
				type="create"
				color="green"
				label="Before Thread"
				size="sm"
				:action="saveDirectiveAction"
				:target="agent"
				:input="{ section: 'Top', name: agent.name + ' Directive' }"
			/>
			<ListTransition name="fade-down-list" data-drop-zone="top-directives-dz">
				<ListItemDraggable
					v-for="agentDirective in topDirectives"
					:key="agentDirective.id"
					:list-items="topDirectives"
					drop-zone="top-directives-dz"
					@update:list-items="onListPositionChange($event, 'Top')"
					@drop-zone="onDropZoneChange"
					@dragstart="onDragStart('Top')"
					@dragend="onDragEnd"
				>
					<SelectableAgentPromptDirectiveCard
						:agent="agent"
						:agent-directive="agentDirective"
						class="my-1"
						:is-removing="removeDirectiveAction.isApplying"
						@remove="removeDirectiveAction.trigger(agent, { id: agentDirective.directive.id })"
						@deleted="dxAgent.routes.details(agent, {directives: true})"
					/>
				</ListItemDraggable>
				<div
					v-if="isDraggingBottom"
					class="text-center text-gray-500 border-dashed border border-slate-500 p-4"
					:class="{'bg-green-900': isDraggingOverTop}"
					@dragenter="isDraggingOverTop = true"
					@dragleave="isDraggingOverTop = false"
				>
					Drag Directive Here
				</div>
			</ListTransition>

			<QSeparator class="bg-slate-400 my-4" />

			<ActionButton
				type="create"
				color="green"
				size="sm"
				label="After Thread"
				:action="saveDirectiveAction"
				:target="agent"
				:input="{ section: 'Bottom', name: agent.name + ' Directive' }"
			/>
			<ListTransition
				name="fade-down-list"
				data-drop-zone="bottom-directives-dz"
			>
				<ListItemDraggable
					v-for="(agentDirective) in bottomDirectives"
					:key="agentDirective.id"
					:list-items="bottomDirectives"
					drop-zone="bottom-directives-dz"
					@update:list-items="onListPositionChange($event, 'Bottom')"
					@drop-zone="onDropZoneChange"
					@dragstart="onDragStart('Bottom')"
					@dragend="onDragEnd"
				>
					<SelectableAgentPromptDirectiveCard
						:agent="agent"
						:agent-directive="agentDirective"
						class="my-1"
						:is-removing="removeDirectiveAction.isApplying"
						@remove="removeDirectiveAction.trigger(agent, { id: agentDirective.directive.id })"
						@deleted="dxAgent.routes.details(agent, {directives: true})"
					/>
				</ListItemDraggable>
				<div
					v-if="isDraggingTop"
					class="text-center text-gray-500 border-dashed border border-slate-500 p-4"
					:class="{'bg-green-900': isDraggingOverBottom}"
					@dragenter="isDraggingOverBottom = true"
					@dragleave="isDraggingOverBottom = false"
				>
					Drag Directive Here
				</div>
			</ListTransition>
		</div>
	</div>
</template>
<script setup lang="ts">
import { dxAgent } from "@/components/Modules/Agents";
import { SelectableAgentPromptDirectiveCard } from "@/components/Modules/Agents/Fields";
import { dxPromptDirective } from "@/components/Modules/Prompts";
import { Agent } from "@/types";
import { ActionButton, ListItemDraggable, ListTransition } from "quasar-ui-danx";
import { computed, ref } from "vue";

const props = defineProps<{
	agent: Agent,
}>();

const saveDirectiveAction = dxAgent.getAction("save-directive", { onFinish: dxPromptDirective.store.refreshItems });
const updateDirectivesAction = dxAgent.getAction("update-directives", { optimistic: true });
const removeDirectiveAction = dxAgent.getAction("remove-directive", {
	optimistic: (action, target: Agent, input) => {
		target.directives = target.directives.filter((d) => d.directive.id !== input.id);
	}
});
const topDirectives = computed(() => props.agent.directives?.filter((directive) => directive.section === "Top") || []);
const bottomDirectives = computed(() => props.agent.directives?.filter((directive) => directive.section === "Bottom") || []);
const isDraggingTop = ref(false);
const isDraggingBottom = ref(false);
const isDraggingOverTop = ref(false);
const isDraggingOverBottom = ref(false);

function onListPositionChange(directives, section) {
	directives = directives.filter((directive) => directive.id);
	const top = section === "Top" ? directives : topDirectives.value;
	const bottom = section === "Bottom" ? directives : bottomDirectives.value;
	const updatedDirectives = [].concat(top).concat(bottom);
	updatedDirectives.forEach((directive, index) => {
		directive.position = index;
	});
	updateDirectivesAction.trigger(props.agent, {
		directives: updatedDirectives
	});
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

function onDragStart(section: "Top" | "Bottom") {
	setTimeout(() => {
		if (section === "Top") isDraggingTop.value = true;
		else isDraggingBottom.value = true;
	}, 200);
}
function onDragEnd() {
	isDraggingOverBottom.value = false;
	isDraggingOverTop.value = false;
	isDraggingTop.value = false;
	isDraggingBottom.value = false;
}
</script>
