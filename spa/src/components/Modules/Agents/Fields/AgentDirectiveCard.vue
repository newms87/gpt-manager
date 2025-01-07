<template>
	<div class="bg-slate-800 rounded-xl cursor-default">
		<div class="px-4 py-2">
			<div class="flex items-center flex-nowrap">
				<ShowHideButton v-model="isEditing" :show-icon="EditIcon" label="" class="bg-sky-800 mr-2" />
				<EditOnClickTextField
					class="flex-grow"
					:model-value="directive.name"
					@update:model-value="updateDebouncedDirectiveAction.trigger(directive, {name: $event})"
				/>
				<QBtn
					class="bg-red-900 ml-4"
					:loading="isRemoving"
					@click="$emit('remove')"
				>
					<RemoveIcon class="w-4" />
				</QBtn>
			</div>
		</div>
		<div v-if="isEditing" class="mt-2">
			<MarkdownEditor
				:model-value="directive.directive_text"
				:max-length="64000"
				@update:model-value="updateDirectiveAction.trigger(directive, {directive_text: $event})"
			/>
		</div>
	</div>
</template>

<script setup lang="ts">
import MarkdownEditor from "@/components/MarkdownEditor/MarkdownEditor";
import { dxPromptDirective } from "@/components/Modules/Prompts/Directives";
import { AgentPromptDirective } from "@/types";
import { FaSolidCircleXmark as RemoveIcon, FaSolidPencil as EditIcon } from "danx-icon";
import { EditOnClickTextField, ShowHideButton } from "quasar-ui-danx";
import { computed, ref } from "vue";

defineEmits(["remove"]);
const props = defineProps<{
	agentDirective: AgentPromptDirective,
	isRemoving: boolean
}>();

const directive = computed(() => props.agentDirective.directive);
const isEditing = ref(false);
const updateDirectiveAction = dxPromptDirective.getAction("update");
const updateDebouncedDirectiveAction = dxPromptDirective.getAction("update-debounced");
</script>
