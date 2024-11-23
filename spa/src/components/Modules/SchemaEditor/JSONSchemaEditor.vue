<template>
	<div class="overflow-auto pb-8">
		<div class="flex items-center flex-nowrap space-x-2">
			<SchemaUndoActions v-model="editableSchema" />
			<ShowHideButton v-model="isShowingRaw" class="bg-slate-700" :show-icon="RawCodeIcon" />
			<SaveStateIndicator :saving="saving" :saved-at="savedAt" class="ml-2" />
		</div>

		<QSeparator class="bg-slate-600 my-4" />

		<div>
			<SchemaObject
				v-if="!isShowingRaw"
				v-model="editableSchema"
				class="min-w-64"
			/>

			<MarkdownEditor
				v-else
				:model-value="editableSchema"
				sync-model-changes
				label=""
				format="yaml"
				@update:model-value="debounceUpdate"
			/>
		</div>
	</div>
</template>
<script setup lang="ts">
import MarkdownEditor from "@/components/MarkdownEditor/MarkdownEditor";
import SchemaObject from "@/components/Modules/SchemaEditor/SchemaObject";
import SchemaUndoActions from "@/components/Modules/SchemaEditor/SchemaUndoActions";
import { JsonSchema } from "@/types";
import { useDebounceFn } from "@vueuse/core";
import { FaSolidCode as RawCodeIcon } from "danx-icon";
import { SaveStateIndicator, ShowHideButton } from "quasar-ui-danx";
import { ref, watch } from "vue";

defineProps<{
	savedAt?: string;
	saving: boolean;
}>();
const schema = defineModel<JsonSchema>();
const editableSchema = ref(schema.value);
const isShowingRaw = ref(false);
const debounceUpdate = useDebounceFn((input: JsonSchema) => {
	schema.value = input;
}, 1000);

// Delay committing saves to the server DB so we don't spam it
watch(() => editableSchema.value, debounceUpdate);
</script>
