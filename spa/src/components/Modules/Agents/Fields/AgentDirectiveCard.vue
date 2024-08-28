<template>
	<div class="bg-slate-800 rounded-xl">
		<div class="px-4 py-2">
			<div class="flex items-center flex-nowrap">
				<ShowHideButton v-model="isEditing" :show-icon="EditIcon" label="" class="bg-sky-800 mr-2" />
				<div class="flex-grow">{{ agentDirective.directive.name }}</div>
				<QBtn
					class="bg-red-900 ml-4"
					:loading="isRemoving"
					@click="$emit('remove')"
				>
					<RemoveIcon class="w-4" />
				</QBtn>
			</div>
		</div>
		<div v-if="isEditing">
			<PromptDirectiveDefinitionPanel :prompt-directive="agentDirective.directive" />
		</div>
	</div>
</template>

<script setup lang="ts">
import { PromptDirectiveDefinitionPanel } from "@/components/Modules/Prompts/Directives/Panels";
import { ShowHideButton } from "@/components/Shared";
import { AgentPromptDirective } from "@/types";
import { FaSolidCircleXmark as RemoveIcon, FaSolidPencil as EditIcon } from "danx-icon";
import { ref } from "vue";

defineEmits(["remove"]);
defineProps<{
	agentDirective: AgentPromptDirective,
	isRemoving: boolean
}>();

const isEditing = ref(false);
</script>
