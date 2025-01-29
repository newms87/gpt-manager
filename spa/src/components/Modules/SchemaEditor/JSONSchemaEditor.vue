<template>
	<div class="flex flex-col flex-nowrap relative" :class="{'h-full': !hideContent}">
		<div class="flex items-center flex-nowrap space-x-2">
			<slot name="header" v-bind="{isShowingRaw}" />
			<div v-if="!hideActions" class="flex items-center flex-nowrap space-x-2">
				<template v-if="!readonly">
					<SchemaUndoActions v-model="editableSchema" />
					<SchemaRevisionHistoryMenu
						v-if="promptSchema"
						:prompt-schema="promptSchema"
						@select="revision => editableSchema = revision.schema"
					/>
				</template>
				<ShowHideButton v-model="isShowingRaw" class="bg-slate-700" :show-icon="RawCodeIcon" />
				<slot name="actions" v-bind="{readonly}" />
				<SaveStateIndicator :saving="saving" :saved-at="savedAt" class="ml-2 w-48" />
			</div>
		</div>

		<QSeparator class="bg-slate-600 my-4" />

		<div v-if="!hideContent" class="flex-grow overflow-y-auto h-full pb-8">
			<SchemaObject
				v-if="!isShowingRaw"
				v-model="editableSchema"
				v-model:sub-selection="fragmentSelector"
				:readonly="readonly"
				:selectable="selectable"
				class="min-w-64"
			/>

			<MarkdownEditor
				v-else
				v-model="editableSchema"
				sync-model-changes
				:readonly="readonly"
				label=""
				:format="promptSchema.schema_format"
			/>
		</div>
		<div
			v-if="loading"
			class="absolute top left w-full h-full flex items-center justify-center bg-slate-400 opacity-20 z-10"
		>
			<QSpinnerGears class="text-sky-900 w-32 h-32" />
		</div>
	</div>
</template>
<script setup lang="ts">
import MarkdownEditor from "@/components/MarkdownEditor/MarkdownEditor";
import SchemaObject from "@/components/Modules/SchemaEditor/SchemaObject";
import SchemaRevisionHistoryMenu from "@/components/Modules/SchemaEditor/SchemaRevisionHistoryMenu";
import SchemaUndoActions from "@/components/Modules/SchemaEditor/SchemaUndoActions";
import { JsonSchema, PromptSchema, SelectionSchema } from "@/types";
import { FaSolidCode as RawCodeIcon } from "danx-icon";
import { SaveStateIndicator, ShowHideButton } from "quasar-ui-danx";
import { ref, watch } from "vue";

defineProps<{
	promptSchema?: PromptSchema;
	savedAt?: string;
	saving: boolean;
	readonly?: boolean;
	hideContent?: boolean;
	hideActions?: boolean;
	selectable?: boolean;
	loading?: boolean;
}>();
const schema = defineModel<JsonSchema>();
const fragmentSelector = defineModel<SelectionSchema | null>("fragmentSelector");
const isShowingRaw = ref(false);

// editableSchema is a 1-way binding to the parent component's schema prop but is initialized w/ the parent's schema value
const editableSchema = ref<JsonSchema>(schema.value || null as JsonSchema);
watch(editableSchema, () => schema.value = editableSchema.value);

watch(schema, () => {
	if (editableSchema.value?.title !== schema.value?.title) {
		editableSchema.value = schema.value;
	}
});
</script>
