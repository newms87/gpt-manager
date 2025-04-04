<template>
	<div class="task-definition-directives-config-field space-y-4 bg-sky-950 p-3 rounded">
		<div class="font-bold text-base">Directives</div>
		<ActionButton
			type="create"
			color="green"
			label="Before Thread"
			size="sm"
			:action="saveDirectiveAction"
			:target="taskDefinition"
			:input="{ section: 'Top', name: taskDefinition.name + ' Directive' }"
		/>
		<ListTransition name="fade-down-list" data-drop-zone="top-directives-dz">
			<ListItemDraggable
				v-for="taskDefinitionDirective in topDirectives"
				:key="taskDefinitionDirective.id"
				:list-items="topDirectives"
				drop-zone="top-directives-dz"
				@update:list-items="onListPositionChange($event, 'Top')"
				@drop-zone="onDropZoneChange"
				@dragstart="onDragStart('Top')"
				@dragend="onDragEnd"
			>
				<PromptDirectiveConfigField
					:model-value="taskDefinitionDirective.directive"
					class="my-1"
					:loading="removeDirectiveAction.isApplying"
					@update:model-value="pd => onDirectiveChange(taskDefinitionDirective, pd)"
					@deleted="dxTaskDefinition.routes.details(taskDefinition, {'*': false, taskDefinitionDirectives: true})"
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
			:target="taskDefinition"
			:input="{ section: 'Bottom', name: taskDefinition.name + ' Directive' }"
		/>
		<ListTransition
			name="fade-down-list"
			data-drop-zone="bottom-directives-dz"
		>
			<ListItemDraggable
				v-for="taskDefinitionDirective in bottomDirectives"
				:key="taskDefinitionDirective.id"
				:list-items="bottomDirectives"
				drop-zone="bottom-directives-dz"
				@update:list-items="onListPositionChange($event, 'Bottom')"
				@drop-zone="onDropZoneChange"
				@dragstart="onDragStart('Bottom')"
				@dragend="onDragEnd"
			>
				<PromptDirectiveConfigField
					:model-value="taskDefinitionDirective.directive"
					class="my-1"
					:loading="removeDirectiveAction.isApplying"
					@update:model-value="pd => onDirectiveChange(taskDefinitionDirective, pd)"
					@deleted="dxTaskDefinition.routes.details(taskDefinition, {'*': false, taskDefinitionDirectives: true})"
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
</template>
<script setup lang="ts">
import { dxPromptDirective } from "@/components/Modules/Prompts";
import { dxTaskDefinition } from "@/components/Modules/TaskDefinitions";
import { PromptDirective, TaskDefinition, TaskDefinitionDirective } from "@/types";
import { ActionButton, ListItemDraggable, ListTransition } from "quasar-ui-danx";
import { computed, ref } from "vue";
import PromptDirectiveConfigField from "./PromptDirectiveConfigField";

const props = defineProps<{
	taskDefinition: TaskDefinition,
}>();

const saveDirectiveAction = dxTaskDefinition.getAction("save-directive", { onFinish: dxPromptDirective.store.refreshItems });
const updateDirectivesAction = dxTaskDefinition.getAction("update-directives", { optimistic: true });
const removeDirectiveAction = dxTaskDefinition.getAction("remove-directive", {
	optimistic: (action, target: TaskDefinition, input) => {
		target.taskDefinitionDirectives = target.taskDefinitionDirectives.filter((d) => d.id !== input.id);
	}
});
const topDirectives = computed(() => props.taskDefinition.taskDefinitionDirectives?.filter((directive) => directive.section === "Top") || []);
const bottomDirectives = computed(() => props.taskDefinition.taskDefinitionDirectives?.filter((directive) => directive.section === "Bottom") || []);
const isDraggingTop = ref(false);
const isDraggingBottom = ref(false);
const isDraggingOverTop = ref(false);
const isDraggingOverBottom = ref(false);

function onDirectiveChange(taskDefinitionDirective: TaskDefinitionDirective, promptDirective?: PromptDirective) {
	if (promptDirective) {
		saveDirectiveAction.trigger(props.taskDefinition, {
			task_definition_directive_id: taskDefinitionDirective.id,
			prompt_directive_id: promptDirective.id
		});
	} else {
		removeDirectiveAction.trigger(props.taskDefinition, { id: taskDefinitionDirective.id });
	}
}
function onListPositionChange(directives, section) {
	directives = directives.filter((directive) => directive.id);
	const top = section === "Top" ? directives : topDirectives.value;
	const bottom = section === "Bottom" ? directives : bottomDirectives.value;
	const updatedDirectives = [].concat(top).concat(bottom);
	updatedDirectives.forEach((directive, index) => {
		directive.position = index;
	});
	updateDirectivesAction.trigger(props.taskDefinition, {
		taskDefinitionDirectives: updatedDirectives
	});
}

function onDropZoneChange(event) {
	const section = event.dropZone.dataset.dropZone === "top-directives-dz" ? "Top" : "Bottom";
	const position = section === "Top" ? topDirectives.value.length + 1 : bottomDirectives.value.length + 1;
	let updatedDirectives = [...props.taskDefinition.taskDefinitionDirectives];
	let index = updatedDirectives.findIndex((directive) => directive.id === event.item.id);
	updatedDirectives.splice(index, 1);
	updatedDirectives.splice(position, 0, { ...event.item, position, section });
	updateDirectivesAction.trigger(props.taskDefinition, {
		taskDefinitionDirectives: updatedDirectives
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
