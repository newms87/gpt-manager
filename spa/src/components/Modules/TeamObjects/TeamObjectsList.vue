<template>
	<div>
		<template v-if="teamObjects?.length > 0">
			<TeamObjectCard
				v-for="teamObject in teamObjects"
				:key="teamObject.id"
				:object="teamObject"
				:schema="promptSchema.schema as JsonSchema"
				class="mt-4 bg-slate-800 rounded"
				@select="dxTeamObject.activatePanel(teamObject, 'workflows')"
			/>

			<div class="flex mt-4">
				<QBtn
					class="px-8 bg-green-900 w-full py-4"
					align="left"
					:loading="createTeamObjectAction.isApplying"
					@click="createTeamObjectAction.trigger(null, { type: teamObjectType })"
				>
					<CreateIcon class="w-4 mr-2" />
					{{ teamObjectType }}
				</QBtn>
			</div>
		</template>
		<template v-else-if="dxTeamObject.isLoadingList.value">
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
import { JsonSchema, PromptSchema } from "@/types";
import { FaSolidPlus as CreateIcon } from "danx-icon";
import { FlashMessages, PanelsDrawer } from "quasar-ui-danx";
import { computed, nextTick, onMounted, ref, watch } from "vue";

const props = defineProps<{ promptSchema: PromptSchema }>();

onMounted(init);
watch(() => props.promptSchema, loadTeamObjects);

const createTeamObjectAction = dxTeamObject.getAction("create");
const teamObjectType = computed(() => props.promptSchema.schema.title);
const teamObjects = computed(() => dxTeamObject.pagedItems.value?.data);
const activeTeamObject = computed(() => dxTeamObject.activeItem.value);
const activePanel = ref("workflows");

async function init() {
	dxTeamObject.initialize();
	await loadTeamObjects();
}

async function loadTeamObjects() {
	if (!teamObjectType.value) {
		return nextTick(() => FlashMessages.error("The active schema does not have a title"));
	}

	dxTeamObject.setActiveFilter({ type: teamObjectType.value });
}
</script>
