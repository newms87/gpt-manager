<template>
	<div class="group rounded overflow-hidden">
		<div class="team-object-header flex items-stretch flex-nowrap gap-x-4">
			<div class="bg-slate-950 text-slate-500 px-3 flex items-center rounded-br-lg">{{ schema?.title }}</div>
			<div class="flex space-x-3 items-center flex-grow">
				<ShowHideButton
					v-if="hasChildren"
					v-model="isShowing"
					label=""
					class="p-3 bg-sky-900"
					@show="onShow"
				/>
				<div class="group font-bold flex items-center gap-2 py-2">
					<EditableDiv
						:model-value="object.name"
						class="rounded-sm text-lg"
						color="slate-800"
						@update:model-value="name => updateAction.trigger(object, {name})"
					/>
					<div v-if="object.date" class="font-sm text-slate-400">
						{{ fDate(object.date) }}
					</div>
					<a
						v-if="object.url"
						target="_blank"
						:href="object.url"
					>
						<LinkIcon class="w-4" />
					</a>
					<QBtn
						class="edit-button bg-slate-700"
						:loading="editAction.isApplying && object.isSaving"
						@click="editAction.trigger(object)"
					>
						<EditIcon class="w-3" />
					</QBtn>
				</div>
			</div>
			<div class="object-controls flex items-center p-2 space-x-3">
				<QBtn class="bg-green-900 text-green-300 p-3" @click="$emit('select')">
					<WorkflowIcon class="w-4" />
				</QBtn>
				<QBtn
					class="p-3 bg-red-900"
					:disable="deleteAction.isApplying"
					@click="deleteAction.trigger(object)"
				>
					<DeleteIcon class="w-3.5" />
				</QBtn>
			</div>
		</div>
		<div class="px-4 py-3">
			<EditableDiv
				:model-value="object.description"
				class="rounded-sm text-slate-500 transition-all"
				color="slate-800"
				placeholder="Enter Description..."
				@update:model-value="description => updateAction.trigger(object, {description})"
			/>
		</div>
		<div v-if="isShowing" class="mt-3 px-4">
			<div class="grid grid-cols-12">
				<TeamObjectAttribute
					v-for="attr in schemaAttributes"
					:key="'attribute-' + attr.name"
					:name="attr.name"
					:title="attr.title"
					:object="object"
					:attribute="object.attributes[attr.name]"
				/>
			</div>
			<div class="mt-5 space-y-4">
				<TeamObjectRelationObject
					v-for="relation in schemaRelationObjects"
					:key="relation.name"
					:name="relation.name"
					:title="relation.title"
					:parent="object"
					:object="object.relations[relation.name] && object.relations[relation.name][0]"
					:schema="properties[relation.name]"
					:level="level + 1"
				/>
			</div>
			<div class="pb-4">
				<template
					v-for="relation in schemaRelationArrays"
					:key="relation.name"
				>
					<TeamObjectRelationArray
						v-if="properties[relation.name]?.items"
						:name="relation.name"
						:title="relation.items?.title || relation.title"
						:parent="object"
						:schema="properties[relation.name].items"
						:relations="object.relations[relation.name] || [] "
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
import {
	FaSolidLink as LinkIcon,
	FaSolidPencil as EditIcon,
	FaSolidTrash as DeleteIcon,
	FaSolidWorm as WorkflowIcon
} from "danx-icon";
import { EditableDiv, fDate, ShowHideButton } from "quasar-ui-danx";
import { computed, ref } from "vue";

defineEmits(["select"]);
const props = withDefaults(defineProps<{
	level?: number,
	object: TeamObject,
	schema: JsonSchema
}>(), {
	level: 0
});

const isShowing = ref(false);
const updateAction = dxTeamObject.getAction("update");
const editAction = dxTeamObject.getAction("edit");
const deleteAction = dxTeamObject.getAction("delete");

const properties = computed(() => props.schema.properties || {});
const hasChildren = computed(() => schemaAttributes.value.length > 0 || schemaRelationArrays.value.length > 0 || schemaRelationObjects.value.length > 0);

/**
 * The list of properties that are attributes
 */
const schemaAttributes = computed(() => {
	const attrs = [];
	for (let name of Object.keys(properties.value)) {
		const attr = properties.value[name];
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
	for (let name of Object.keys(properties.value)) {
		const attr = properties.value[name];
		// Any array or object types are the relations of interest for the object
		if (attr.type !== "array") continue;
		relations.push({ name, ...attr });
	}
	return relations;
});

/** The list of properties that are relations */
const schemaRelationObjects = computed(() => {
	const relations = [];
	for (let name of Object.keys(properties.value)) {
		const attr = properties.value[name];
		// Any array or object types are the relations of interest for the object
		if (attr.type !== "object") continue;
		relations.push({ name, ...attr });
	}
	return relations;
});

async function onShow() {
	await dxTeamObject.routes.detailsAndStore(props.object);
}
</script>

<style lang="scss" scoped>
.team-object-header {
	.edit-button {
		transition: all 0.3s;
		opacity: 0;
	}

	&:hover {
		.edit-button {
			opacity: 1;
		}
	}
}
</style>
