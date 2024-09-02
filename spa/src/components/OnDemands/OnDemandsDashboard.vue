<template>
	<div class="h-full p-6 overflow-y-auto">
		<SelectOrCreateField
			v-model:editing="isEditingSchema"
			:selected="activeSchema"
			show-edit
			:can-edit="!!activeSchema"
			:options="dxPromptSchema.pagedItems.value?.data || []"
			:loading="createAction.isApplying"
			select-by-object
			option-label="name"
			@create="onCreate"
			@update:selected="onSelectPromptSchema"
		/>

		<div v-if="isEditingSchema">
			<MarkdownEditor
				:model-value="activeSchema.schema"
				sync-model-changes
				class="mt-4"
				label=""
				:format="activeSchema.schema_format"
				@update:model-value="updateDebouncedSchemaAction.trigger(activeSchema, { schema: $event })"
			/>
		</div>

		<div v-if="activeSchema">
			<template v-if="teamObjects.length > 0">
				<TeamObjectCard
					v-for="teamObject in teamObjects"
					:key="teamObject.id"
					:object="teamObject"
					class="mt-4"
				/>
			</template>
			<template v-else-if="isLoading">
				<QSkeleton
					v-for="i in 3"
					:key="i"
					class="mt-4"
					height="5em"
				/>
			</template>
			<template v-else>
				<div v-if="activeSchema.schema.title" class="mt-4">
					No team objects found for {{ activeSchema.schema.title }}
				</div>
				<div v-else>
					Please update the schema to include the title property at the top level
				</div>
			</template>
		</div>
	</div>
</template>
<script setup lang="ts">
import MarkdownEditor from "@/components/MardownEditor/MarkdownEditor";
import { dxPromptSchema, type PromptSchemaPagedItems } from "@/components/Modules/Prompts/Schemas/config";
import { getAction } from "@/components/Modules/Prompts/Schemas/config/actions";
import { TeamObjectCard, TeamObjectRoutes } from "@/components/Modules/TeamObjects";
import { FlashMessages, getItem, SelectOrCreateField, setItem } from "quasar-ui-danx";
import { computed, nextTick, onMounted, ref } from "vue";

const PROMPT_SCHEMA_STORED_KEY = "dx-prompt-schema";

onMounted(init);

const isLoading = ref(false);
const createAction = getAction("create");
const updateDebouncedSchemaAction = getAction("update-debounced");
const isEditingSchema = ref(false);
const teamObjects = ref([]);

const activeSchema = computed(() => dxPromptSchema.activeItem.value);
async function onCreate() {
	await createAction.trigger(activeSchema.value);
}

async function init() {
	dxPromptSchema.initialize();
	dxPromptSchema.setActiveItem(getItem(PROMPT_SCHEMA_STORED_KEY));

	if (activeSchema.value) {
		await loadTeamObjects();
	}
}

async function onSelectPromptSchema(promptSchema) {
	dxPromptSchema.setActiveItem(promptSchema);
	setItem(PROMPT_SCHEMA_STORED_KEY, promptSchema);
	await loadTeamObjects();
}

async function loadTeamObjects() {
	if (!activeSchema.value) return;

	const type = activeSchema.value.schema.title;

	if (!type) {
		return nextTick(() => FlashMessages.error("The active schema does not have a title"));
	}

	isLoading.value = true;
	const results = await TeamObjectRoutes.list({ filter: { type } }) as PromptSchemaPagedItems;
	teamObjects.value = results.data;
	isLoading.value = false;
}
</script>
