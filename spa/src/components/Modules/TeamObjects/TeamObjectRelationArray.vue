<template>
	<div :data-testid="name">
		<div class="flex items-stretch flex-nowrap gap-4">
			<ShowHideButton
				v-model="isShowing"
				:label="(title || name) + ': ' + relations.length + ' items'"
				class="py-2 px-6 bg-sky-900"
			/>
			<QBtn
				class="bg-green-900 p-2.5"
				:loading="createAction.isApplying"
				@click="createAction.trigger(parent, {type: schema.title, relationship_name: name, name: title})"
			>
				<CreateIcon class="w-4" />
			</QBtn>
		</div>
		<div v-if="isShowing">
			<template
				v-for="(relation, index) in relations"
				:key="relation.id"
			>
				<TeamObjectCard
					:object="relation"
					:schema="schema"
					:class="levelObjectClass"
					:level="level"
					@merge="showMergeDialog(relation)"
				/>

				<QSeparator v-if="index < relations.length - 1" :class="levelSeparatorClass" />
			</template>
		</div>

		<TeamObjectMergeDialog
			v-model="showMergeDialogRef"
			:source-object="sourceTeamObject"
			:available-objects="availableMergeTargets"
			@merge="performMerge"
		/>
	</div>
</template>
<script setup lang="ts">
import { dxTeamObject } from "@/components/Modules/TeamObjects/config";
import { TeamObject } from "@/components/Modules/TeamObjects/team-objects";
import TeamObjectCard from "@/components/Modules/TeamObjects/TeamObjectCard";
import TeamObjectMergeDialog from "@/components/Modules/TeamObjects/TeamObjectMergeDialog.vue";
import { JsonSchema } from "@/types";
import { FaSolidPlus as CreateIcon } from "danx-icon";
import { apiUrls } from "@/api";
import { request, ShowHideButton } from "quasar-ui-danx";
import { computed, ref } from "vue";

const props = withDefaults(defineProps<{
	name: string,
	title?: string,
	schema: JsonSchema,
	parent: TeamObject,
	relations: TeamObject[],
	objectClass?: string;
	separatorClass?: string,
	level?: number
}>(), {
	level: 0,
	title: "",
	objectClass: "",
	separatorClass: ""
});

const isShowing = ref(false);
const createAction = dxTeamObject.getAction("create-relation");

const levelClassSettings = {
	0: {
		object: "bg-sky-950 mt-4",
		separator: "hidden"
	},
	1: {
		object: "bg-sky-950 mt-4",
		separator: "hidden"
	},
	2: {
		object: "mt-1",
		separator: "mt-1 bg-slate-400"
	},
	3: {
		object: "mt-1",
		separator: "mt-1 bg-slate-400"
	}
};

const levelClass = computed(() => levelClassSettings[props.level] || levelClassSettings[3]);
const levelObjectClass = computed(() => props.objectClass || levelClass.value.object);
const levelSeparatorClass = computed(() => props.separatorClass || levelClass.value.separator);

const showMergeDialogRef = ref(false);
const sourceTeamObject = ref<TeamObject | null>(null);

const availableMergeTargets = computed(() => {
	if (!sourceTeamObject.value) return [];
	return props.relations.filter(obj => obj.id !== sourceTeamObject.value?.id);
});

function showMergeDialog(teamObject: TeamObject) {
	sourceTeamObject.value = teamObject;
	showMergeDialogRef.value = true;
}

async function performMerge(sourceObject: TeamObject, targetObject: TeamObject) {
	try {
		const response = await request.post(apiUrls.teams.mergeObjects({ sourceId: sourceObject.id, targetId: targetObject.id }));

		if (response) {
			showMergeDialogRef.value = false;
			sourceTeamObject.value = null;
			await dxTeamObject.routes.details(props.parent);
		}
	} catch (error) {
		console.error("Merge error:", error);
	}
}
</script>
