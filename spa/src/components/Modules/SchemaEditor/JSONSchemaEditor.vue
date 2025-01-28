<template>
	<div class="flex flex-col flex-nowrap" :class="{'h-full': !hideContent}">
		<div class="flex items-center flex-nowrap space-x-2">
			<slot name="header" v-bind="{isShowingRaw}" />
			<div class="flex items-center flex-nowrap space-x-2">
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
				v-model:sub-selection="subSelection"
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
	</div>
</template>
<script setup lang="ts">
import MarkdownEditor from "@/components/MarkdownEditor/MarkdownEditor";
import SchemaObject from "@/components/Modules/SchemaEditor/SchemaObject";
import SchemaRevisionHistoryMenu from "@/components/Modules/SchemaEditor/SchemaRevisionHistoryMenu";
import SchemaUndoActions from "@/components/Modules/SchemaEditor/SchemaUndoActions";
import { JsonSchema, PromptSchema, SelectionSchema } from "@/types";
import { FaSolidCode as RawCodeIcon } from "danx-icon";
import { cloneDeep, SaveStateIndicator, ShowHideButton } from "quasar-ui-danx";
import { ref, watch } from "vue";

defineProps<{
	promptSchema?: PromptSchema;
	savedAt?: string;
	saving: boolean;
	readonly?: boolean;
	hideContent?: boolean;
	selectable?: boolean;
}>();
const schema = defineModel<JsonSchema>();
const subSelection = defineModel<SelectionSchema | null>("subSelection");
const editableSchema = ref(schema.value || {});
const isShowingRaw = ref(false);

watch(() => editableSchema.value, () => schema.value = cloneDeep(editableSchema.value));
</script>
