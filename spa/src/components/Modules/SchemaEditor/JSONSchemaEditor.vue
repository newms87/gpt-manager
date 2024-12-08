<template>
	<div class="flex flex-col flex-nowrap h-full pb-8">
		<div class="flex items-center flex-nowrap space-x-2">
			<SchemaRevisionHistoryMenu
				v-if="promptSchema"
				:prompt-schema="promptSchema"
				@select="revision => editableSchema = revision.schema"
			/>
			<SchemaUndoActions v-model="editableSchema" />
			<ShowHideButton v-model="isShowingRaw" class="bg-slate-700" :show-icon="RawCodeIcon" />
			<SaveStateIndicator :saving="saving" :saved-at="savedAt" class="ml-2" />
		</div>

		<QSeparator class="bg-slate-600 my-4" />

		<div class="flex-grow overflow-y-auto h-full pb-8">
			<SchemaObject
				v-if="!isShowingRaw"
				v-model="editableSchema"
				class="min-w-64"
			/>

			<MarkdownEditor
				v-else
				v-model="editableSchema"
				sync-model-changes
				label=""
				format="yaml"
			/>
		</div>
	</div>
</template>
<script setup lang="ts">
import MarkdownEditor from "@/components/MarkdownEditor/MarkdownEditor";
import SchemaObject from "@/components/Modules/SchemaEditor/SchemaObject";
import SchemaRevisionHistoryMenu from "@/components/Modules/SchemaEditor/SchemaRevisionHistoryMenu";
import SchemaUndoActions from "@/components/Modules/SchemaEditor/SchemaUndoActions";
import { JsonSchema, PromptSchema } from "@/types";
import { FaSolidCode as RawCodeIcon } from "danx-icon";
import { cloneDeep, SaveStateIndicator, ShowHideButton } from "quasar-ui-danx";
import { ref, watch } from "vue";

defineProps<{
	promptSchema?: PromptSchema;
	savedAt?: string;
	saving: boolean;
}>();
const schema = defineModel<JsonSchema>();
const editableSchema = ref(schema.value || {});
const isShowingRaw = ref(false);

watch(() => editableSchema.value, () => schema.value = cloneDeep(editableSchema.value));
</script>
