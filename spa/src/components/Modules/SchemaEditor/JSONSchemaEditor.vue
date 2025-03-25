<template>
	<div class="flex flex-col flex-nowrap relative" :class="{'h-full': !hideContent}">
		<div class="flex-x space-x-2">
			<slot name="header" v-bind="{isShowingRaw}" />
			<div v-if="!hideActions" class="flex-x space-x-2">
				<template v-if="!readonly">
					<SchemaUndoActions v-model="editableSchema" />
					<SchemaRevisionHistoryMenu
						v-if="schemaDefinition"
						:schema-definition="schemaDefinition"
						@select="revision => editableSchema = revision.schema"
					/>
				</template>
				<ShowHideButton
					v-if="!hideContent && toggleRawJson"
					v-model="isShowingRaw"
					class="bg-slate-700"
					:show-icon="RawCodeIcon"
				/>
				<slot name="actions" v-bind="{readonly}" />
				<SaveStateIndicator v-if="!hideSaveState" :saving="saving" :saved-at="savedAt" class="ml-2 w-48" />
			</div>
		</div>

		<QSeparator v-if="isSchemaVisible && !dialog" class="bg-slate-600 my-4" />

		<div v-if="isSchemaVisible && !dialog" class="flex-grow overflow-y-auto h-full pb-8">
			<SchemaObject
				v-if="!isShowingRaw"
				v-model="editableSchema"
				v-model:fragment-selector="fragmentSelector"
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
				:format="schemaDefinition.schema_format"
			/>
		</div>
		<FullScreenDialog
			v-if="isSchemaVisible && dialog"
			model-value
			closeable
			content-class="bg-slate-900 p-8"
			@close="$emit('close')"
		>
			<SchemaObject
				v-if="!isShowingRaw"
				v-model="editableSchema"
				v-model:fragment-selector="fragmentSelector"
				:readonly="readonly"
				:readonly-description="readonlyDescription"
				:selectable="selectable"
				class="min-w-64"
			/>

			<MarkdownEditor
				v-else
				v-model="editableSchema"
				sync-model-changes
				:readonly="readonly"
				label=""
				:format="schemaDefinition.schema_format"
			/>
		</FullScreenDialog>
		<div
			v-if="loading"
			class="absolute top left w-full h-full flex items-center justify-center bg-slate-400 opacity-20 z-10"
		>
			<QSpinnerGears v-if="hideContent" class="text-sky-900 w-6 h-6" />
			<QSpinnerGears v-else class="text-sky-900 w-32 h-32" />
		</div>
	</div>
</template>
<script setup lang="ts">
import MarkdownEditor from "@/components/MarkdownEditor/MarkdownEditor";
import SchemaObject from "@/components/Modules/SchemaEditor/SchemaObject";
import SchemaRevisionHistoryMenu from "@/components/Modules/SchemaEditor/SchemaRevisionHistoryMenu";
import SchemaUndoActions from "@/components/Modules/SchemaEditor/SchemaUndoActions";
import { FragmentSelector, JsonSchema, SchemaDefinition } from "@/types";
import { FaSolidCode as RawCodeIcon } from "danx-icon";
import { FullScreenDialog, SaveStateIndicator, ShowHideButton } from "quasar-ui-danx";
import { computed, ref, watch } from "vue";

defineEmits<{ close: void }>();
const props = defineProps<{
	schemaDefinition?: SchemaDefinition;
	savedAt?: string;
	saving?: boolean;
	readonly?: boolean;
	readonlyDescription?: boolean;
	hideContent?: boolean;
	hideActions?: boolean;
	selectable?: boolean;
	previewable?: boolean;
	toggleRawJson?: boolean;
	loading?: boolean;
	dialog?: boolean;
	hideSaveState?: boolean;
}>();

const schema = defineModel<JsonSchema>();
const fragmentSelector = defineModel<FragmentSelector | null>("fragmentSelector");
const isShowingRaw = ref(false);
const isSchemaVisible = computed(() => !props.hideContent && !!editableSchema.value);

// editableSchema is a 1-way binding to the parent component's schema prop but is initialized w/ the parent's schema value
const editableSchema = ref<JsonSchema>(schema.value || null as JsonSchema);
watch(editableSchema, () => {
	// If there has been a change, then update the original schema
	if (JSON.stringify(editableSchema.value) !== JSON.stringify(schema.value)) {
		schema.value = editableSchema.value;
	}
});

watch(schema, () => {
	if (editableSchema.value?.title !== schema.value?.title) {
		editableSchema.value = schema.value;
	}
});
</script>
