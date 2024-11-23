<template>
	<div class="overflow-auto pb-8">
		<div class="flex items-center flex-nowrap">
			<ShowHideButton v-model="isShowingRaw" class="bg-slate-700" :show-icon="RawCodeIcon" />
			<SaveStateIndicator :saving="saving" :saved-at="savedAt" class="ml-2" />
		</div>

		<QSeparator class="bg-slate-600 my-4" />

		<div>
			<SchemaObject v-if="!isShowingRaw" v-model="schema" class="min-w-64" />

			<MarkdownEditor
				v-else
				:model-value="schema"
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
import { JsonSchema } from "@/types";
import { useDebounceFn } from "@vueuse/core";
import { FaSolidCode as RawCodeIcon } from "danx-icon";
import { SaveStateIndicator, ShowHideButton } from "quasar-ui-danx";
import { ref } from "vue";

defineProps<{
	savedAt?: string;
	saving: boolean;
}>();
const schema = defineModel<JsonSchema>();
const isShowingRaw = ref(false);
const debounceUpdate = useDebounceFn((input: JsonSchema) => {
	schema.value = input;
}, 1000);
</script>
