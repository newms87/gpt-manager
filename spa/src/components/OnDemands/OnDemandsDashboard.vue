<template>
	<div class="h-full p-6 overflow-y-auto">
		<div class="flex items-stretch flex-nowrap gap-4">
			<SelectOrCreateField
				v-model:editing="isEditingSchema"
				:selected="activeSchema"
				show-edit
				:can-edit="!!activeSchema"
				:options="dxPromptSchema.pagedItems.value?.data || []"
				:loading="createSchemaAction.isApplying"
				select-by-object
				option-label="name"
				create-text=""
				class="w-1/2"
				@create="onCreate"
				@update:selected="onSelectPromptSchema"
			/>
			<div class="w-1/2 flex justify-end">
				<QBtn
					class="px-8 bg-green-900"
					:loading="createTeamObjectAction.isApplying"
					@click="createTeamObjectAction.trigger(null, { type: teamObjectType })"
				>
					<CreateIcon class="w-4 mr-2" />
					{{ teamObjectType }}
				</QBtn>
			</div>
		</div>

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
			<template v-if="teamObjects?.length > 0">
				<TeamObjectCard
					v-for="teamObject in teamObjects"
					:key="teamObject.id"
					:object="teamObject"
					:schema="activeSchema.schema as JsonSchema"
					class="mt-4 bg-slate-800 rounded-lg"
				/>
			</template>
			<template v-else-if="dxTeamObject.isLoadingList">
				<QSkeleton
					v-for="i in 3"
					:key="i"
					class="mt-4"
					height="5em"
				/>
			</template>
			<template v-else>
				<div v-if="teamObjectType" class="mt-4">
					No {{ teamObjectType }} objects found. Try creating a new one
				</div>
				<div v-else>
					Please update the schema to include the title property at the top level
				</div>
			</template>
		</div>
	</div>
</template>
<script setup lang="ts">
import MarkdownEditor from "@/components/MarkdownEditor/MarkdownEditor";
import { dxPromptSchema } from "@/components/Modules/Prompts/Schemas";
import { dxTeamObject, TeamObjectCard } from "@/components/Modules/TeamObjects";
import { JsonSchema } from "@/types";
import { FaSolidPlus as CreateIcon } from "danx-icon";
import { FlashMessages, getItem, SelectOrCreateField, setItem } from "quasar-ui-danx";
import { computed, nextTick, onMounted, ref } from "vue";

const PROMPT_SCHEMA_STORED_KEY = "dx-prompt-schema";

onMounted(init);

const createSchemaAction = dxPromptSchema.getAction("create");
const updateDebouncedSchemaAction = dxPromptSchema.getAction("update-debounced");
const createTeamObjectAction = dxTeamObject.getAction("create");
const isEditingSchema = ref(false);

const activeSchema = computed(() => dxPromptSchema.activeItem.value);
const teamObjectType = computed(() => activeSchema.value?.schema.title);
const teamObjects = computed(() => dxTeamObject.pagedItems.value?.data);

async function onCreate() {
	await createSchemaAction.trigger(activeSchema.value);
}

async function init() {
	dxPromptSchema.initialize();
	dxTeamObject.initialize();
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

	if (!teamObjectType.value) {
		return nextTick(() => FlashMessages.error("The active schema does not have a title"));
	}

	dxTeamObject.setActiveFilter({ type: teamObjectType.value });
}
</script>
