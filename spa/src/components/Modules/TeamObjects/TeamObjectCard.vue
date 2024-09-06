<template>
	<div class="p-3 bg-slate-800 rounded">
		<div class="team-object-header flex items-center flex-nowrap gap-x-4">
			<div class="flex-grow">
				<div class="font-bold flex items-center gap-2">
					<div>{{ object.name }}</div>
					<div v-if="object.date" class="font-sm text-slate-400">
						{{ fDate(object.date) }}
					</div>
					<a v-if="object.url" target="_blank" :href="object.url">
						<LinkIcon class="w-4" />
					</a>
				</div>
				<div class="mt-1">
					{{ object.description }}
				</div>
			</div>
			<ShowHideButton v-model="isShowing" label="Show" class="py-2 px-6 bg-sky-900" @show="onShow" />
			<QBtn
				class="p-3 bg-sky-900"
				:disable="editAction.isApplying"
				@click="editAction.trigger(object)"
			>
				<EditIcon class="w-4" />
			</QBtn>
			<QBtn
				class="p-3 bg-red-900"
				:disable="deleteAction.isApplying"
				@click="deleteAction.trigger(object)"
			>
				<DeleteIcon class="w-3.5" />
			</QBtn>
		</div>
		<div v-if="isShowing" class="mt-5">
			<div class="grid grid-cols-12">
				<TeamObjectAttribute
					v-for="attr in schemaAttributes"
					:key="'attribute-' + attr.name"
					:name="attr.name"
					:title="attr.title"
					:attribute="object[attr.name]"
				/>
			</div>
			<div class="mt-5">
				<TeamObjectRelationObject
					v-for="relation in schemaRelationObjects"
					:key="relation.name"
					:name="relation.name"
					:title="relation.title"
					:object="object[relation.name]"
					:schema="schema.properties[relation.name]"
				/>
			</div>
			<div class="mt-5">
				<TeamObjectRelationArray
					v-for="relation in schemaRelationArrays"
					:key="relation.name"
					:name="relation.name"
					:title="relation.title"
					:schema="schema.properties[relation.name]?.items"
					:relations="object[relation.name] || []"
				/>
			</div>
		</div>
	</div>
</template>
<script setup lang="ts">
import { dxTeamObject } from "@/components/Modules/TeamObjects";
import { TeamObject } from "@/components/Modules/TeamObjects/team-objects";
import TeamObjectAttribute from "@/components/Modules/TeamObjects/TeamObjectAttribute";
import TeamObjectRelationArray from "@/components/Modules/TeamObjects/TeamObjectRelationArray";
import TeamObjectRelationObject from "@/components/Modules/TeamObjects/TeamObjectRelationObject";
import { JsonSchema } from "@/types";
import { FaSolidLink as LinkIcon, FaSolidPencil as EditIcon, FaSolidTrash as DeleteIcon } from "danx-icon";
import { fDate, ShowHideButton } from "quasar-ui-danx";
import { computed, ref } from "vue";

const props = defineProps<{ object: TeamObject, schema: JsonSchema }>();

const isShowing = ref(false);
const editAction = dxTeamObject.getAction("edit");
const deleteAction = dxTeamObject.getAction("delete");

/**
 * The list of properties that are attributes
 */
const schemaAttributes = computed(() => {
	const attrs = [];
	for (let name of Object.keys(props.schema.properties)) {
		const attr = props.schema.properties[name];
		// Any scalar types excluding the base attribute types (ie: name, date, etc.) are the attributes of interest for the object
		if (attr.type === "array" || attr.type === "object" || ["name", "date", "description", "meta"].includes(name)) {
			continue;
		}
		attrs.push({
			name,
			...attr
		});
	}
	return attrs;
});

/** The list of properties that are relations */
const schemaRelationArrays = computed(() => {
	const relations = [];
	for (let name of Object.keys(props.schema.properties)) {
		const attr = props.schema.properties[name];
		// Any array or object types are the relations of interest for the object
		if (attr.type !== "array") {
			continue;
		}
		console.log("relation", name, attr);
		relations.push({ name, ...attr });
	}
	return relations;
});

/** The list of properties that are relations */
const schemaRelationObjects = computed(() => {
	const relations = [];
	for (let name of Object.keys(props.schema.properties)) {
		const attr = props.schema.properties[name];
		// Any array or object types are the relations of interest for the object
		if (attr.type !== "object") {
			continue;
		}
		relations.push({
			name,
			...attr
		});
	}
	return relations;
});

async function onShow() {
	await dxTeamObject.routes.detailsAndStore(props.object);
}
</script>
