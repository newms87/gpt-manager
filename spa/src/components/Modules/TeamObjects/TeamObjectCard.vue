<template>
	<div class="p-3">
		<div class="team-object-header flex items-center flex-nowrap gap-x-4">
			<div class="flex-grow">
				<div class="group font-bold flex items-center gap-2">
					<EditableDiv
						:model-value="object.name"
						class="rounded-sm"
						color="slate-800"
						@update:model-value="name => updateAction.trigger(object, {name})"
					/>
					<div v-if="object.date" class="font-sm text-slate-400">
						{{ fDate(object.date) }}
					</div>
					<a target="_blank" :href="object.url" :class="{'opacity-0 group-hover:opacity-1': !object.url}">
						<LinkIcon class="w-4" />
					</a>
				</div>
				<div class="mt-1">
					{{ object.description }}
				</div>
			</div>
			<ShowHideButton
				v-if="hasChildren"
				v-model="isShowing"
				label="Show"
				class="py-2 px-6 bg-sky-900"
				@show="onShow"
			/>
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
					:object="object"
					:attribute="object[attr.name]"
				/>
			</div>
			<div class="mt-5">
				<TeamObjectRelationObject
					v-for="relation in schemaRelationObjects"
					:key="relation.name"
					:name="relation.name"
					:title="relation.title"
					:parent="object"
					:object="object[relation.name] && object[relation.name][0]"
					:schema="schema.properties[relation.name]"
					:level="level + 1"
				/>
			</div>
			<div>
				<template
					v-for="relation in schemaRelationArrays"
					:key="relation.name"
				>
					<TeamObjectRelationArray
						v-if="schema.properties[relation.name]?.items"
						:name="relation.name"
						:title="relation.title"
						:parent="object"
						:schema="schema.properties[relation.name].items"
						:relations="object[relation.name] || []"
						:level="level + 1"
						class="mt-5"
					/>
					<div v-else class="mt-5 p-3 rounded bg-red-200 text-red-900">Missing child schema {{ relation.name }} in
						schema
						properties
					</div>
				</template>
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
import { FaSolidLink as LinkIcon, FaSolidTrash as DeleteIcon } from "danx-icon";
import { EditableDiv, fDate, ShowHideButton } from "quasar-ui-danx";
import { computed, ref } from "vue";

const props = withDefaults(defineProps<{
	level?: number,
	object: TeamObject,
	schema: JsonSchema
}>(), {
	level: 0
});

const isShowing = ref(false);
const updateAction = dxTeamObject.getAction("update");
const deleteAction = dxTeamObject.getAction("delete");

const hasChildren = computed(() => schemaAttributes.value.length > 0 || schemaRelationArrays.value.length > 0 || schemaRelationObjects.value.length > 0);

/**
 * The list of properties that are attributes
 */
const schemaAttributes = computed(() => {
	const attrs = [];
	for (let name of Object.keys(props.schema.properties)) {
		const attr = props.schema.properties[name];
		// Any scalar types excluding the base attribute types (ie: name, date, etc.) are the attributes of interest for the object
		if (attr.type === "array" || attr.type === "object" || ["name", "date", "url", "description", "meta"].includes(name)) {
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
		if (attr.type !== "array") continue;
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
		if (attr.type !== "object") continue;
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
