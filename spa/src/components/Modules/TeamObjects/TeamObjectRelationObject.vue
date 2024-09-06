<template>
	<div :data-testid="name" class="bg-slate-900 py-2 px-4 rounded-lg">
		<div class="flex items-center flex-nowrap">
			<div class="font-bold">{{ title || name }}</div>
			<div class="flex-grow" />
			<QBtn v-if="!object" @click="createAction.trigger(parent, {type: schema.title, relationship_name: name})">
				<CreateIcon class="w-4" />
			</QBtn>
		</div>
		<TeamObjectCard v-if="object" :object="object" :schema="schema" :level="level" class="bg-slate-900 rounded" />
		<div v-else class="mt-2">
			No {{ name }} found.
		</div>
	</div>
</template>
<script setup lang="ts">
import { dxTeamObject } from "@/components/Modules/TeamObjects/config";
import { TeamObject } from "@/components/Modules/TeamObjects/team-objects";
import TeamObjectCard from "@/components/Modules/TeamObjects/TeamObjectCard";
import { JsonSchema } from "@/types";
import { FaSolidPlus as CreateIcon } from "danx-icon";

withDefaults(defineProps<{
	name: string,
	title?: string,
	level?: number,
	parent: TeamObject,
	object?: TeamObject,
	schema: JsonSchema
}>(), {
	level: 0,
	object: null,
	title: ""
});
const createAction = dxTeamObject.getAction("create-relation");
</script>
