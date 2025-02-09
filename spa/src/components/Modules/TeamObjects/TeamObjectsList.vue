<template>
	<div>
		<div v-if="teamObjectType" class="mt-4">
			<QBtn
				class="px-4 bg-green-900 text-sm py-3"
				align="left"
				:loading="createTeamObjectAction.isApplying"
				@click="createTeamObjectAction.trigger(null, { type: teamObjectType, prompt_schema_id: promptSchema.id })"
			>
				<CreateIcon class="w-4 mr-2" />
				{{ teamObjectType }}
			</QBtn>
		</div>
		<QBanner v-else class="bg-red-800 text-slate-300 mt-8">
			Please update the schema to include the title property at the top level
		</QBanner>

		<template v-if="dxTeamObject.isLoadingList.value && !teamObjects?.length">
			<QSkeleton
				v-for="i in 3"
				:key="i"
				class="mt-4"
				height="5em"
			/>
		</template>
		<template v-else-if="teamObjects?.length > 0">
			<TeamObjectCard
				v-for="teamObject in teamObjects"
				:key="teamObject.id"
				:object="teamObject"
				:schema="promptSchema.schema || {} as JsonSchema"
				class="mt-4 bg-slate-800 rounded"
				@select="dxTeamObject.activatePanel(teamObject, 'workflows')"
			/>
		</template>

		<PanelsDrawer
			v-if="activeTeamObject"
			:title="activeTeamObject.name"
			:model-value="activePanel"
			:target="activeTeamObject"
			:panels="dxTeamObject.panels"
			@update:model-value="panel => dxTeamObject.activatePanel(activeTeamObject, panel)"
			@close="dxTeamObject.setActiveItem(null)"
		/>
	</div>
</template>
<script setup lang="ts">
import { dxTeamObject, TeamObjectCard } from "@/components/Modules/TeamObjects";
import { JsonSchema, SchemaDefinition } from "@/types";
import { FaSolidPlus as CreateIcon } from "danx-icon";
import { PanelsDrawer } from "quasar-ui-danx";
import { computed, onMounted, ref, watch } from "vue";

const props = defineProps<{ promptSchema: SchemaDefinition }>();

onMounted(init);
watch(() => props.promptSchema, loadTeamObjects);

const createTeamObjectAction = dxTeamObject.getAction("create");
const teamObjectType = computed(() => props.promptSchema?.schema?.title);
const teamObjects = computed(() => dxTeamObject.pagedItems.value?.data);
const activeTeamObject = computed(() => dxTeamObject.activeItem.value);
const activePanel = ref("workflows");

async function init() {
	dxTeamObject.setActiveFilter({ type: teamObjectType.value });

	dxTeamObject.initialize({
		isDetailsEnabled: false,
		isListEnabled: false,
		isSummaryEnabled: false,
		isFieldOptionsEnabled: false
	});

	await loadTeamObjects();
}

async function loadTeamObjects() {
	// If the team object type is not set, do not load any team objects and clear the current results
	if (!teamObjectType.value) {
		dxTeamObject.pagedItems.value = null;
		dxTeamObject.setOptions({ isListEnabled: false });
		return;
	}

	// Trigger loading the new team objects
	dxTeamObject.setOptions({ isListEnabled: true });
	dxTeamObject.setActiveFilter({ type: teamObjectType.value });
}
</script>
