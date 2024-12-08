<template>
	<div :data-testid="name" class="bg-slate-900 overflow-hidden rounded-md">
		<TeamObjectCard
			v-if="object"
			:object="object"
			:schema="schema"
			:level="level"
			class="bg-slate-900"
		/>
		<QBtn
			v-else
			class="p-4 w-full text-left"
			align="left"
			@click="createAction.trigger(parent, {type: schema.title, relationship_name: name})"
		>
			<CreateIcon class="w-4 mr-2" />
			{{ title || name }}
		</QBtn>
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
