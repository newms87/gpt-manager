<template>
	<div class="h-full">
		<SelectOrCreateField
			v-model:editing="isEditingSchema"
			:selected="PromptSchemaController.activeItem"
			:show-edit="!!PromptSchemaController.activeItem"
			:options="PromptSchemaController.pagedItems.value?.data || []"
			:loading="createAction.isApplying"
			select-by-object
			option-label="name"
			@create="onCreate"
			@update:selected="PromptSchemaController.activeItem.value = $event"
		/>

		<div>
			Active:
			{{ PromptSchemaController.activeItem }}
		</div>
	</div>
</template>
<script setup lang="ts">
import { getAction } from "@/components/Modules/Prompts/Schemas/promptSchemaActions";
import { PromptSchemaController } from "@/components/Modules/Prompts/Schemas/promptSchemaControls";
import { SelectOrCreateField } from "quasar-ui-danx";
import { onMounted, ref } from "vue";

onMounted(PromptSchemaController.initialize);

const createAction = getAction("create");
const isEditingSchema = ref(false);
async function onCreate() {
	await createAction.trigger(PromptSchemaController.activeItem.value);
}
</script>
