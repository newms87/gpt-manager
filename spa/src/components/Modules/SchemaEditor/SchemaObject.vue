<template>
	<div class="schema-object flex items-start flex-nowrap">
		<div class="parent-object bg-slate-700 rounded-lg overflow-hidden inline-block w-96 flex-shrink-0">
			<div class="flex items-start flex-nowrap px-4 py-2 bg-slate-800">
				<QCheckbox
					v-if="selectable && relationName !== 'root'"
					dense
					:model-value="isSelected"
					class="mr-2 py-1"
					@update:model-value="changeSelection"
				/>
				<div v-if="$slots.header" class="flex-grow">
					<slot name="header" />
				</div>
				<div class="py-1">
					<EditableDiv
						v-if="!hideHeader"
						:readonly="readonly"
						:model-value="schemaObject.title || ''"
						color="slate-600"
						class="min-w-20"
						placeholder="(Enter Title)"
						:debounce-delay="1000"
						@update:model-value="title => onUpdate({title})"
					/>
				</div>
			</div>
			<div v-if="!readonly || customPropertyNames.length > 0" class="py-2 px-4">
				<ListTransition
					name="fade-down-list"
					:data-drop-zone="`custom-props-${schemaObject.id}-dz`"
				>
					<ListItemDraggable
						v-for="name in customPropertyNames"
						:key="`property-${objectProperties[name].id}`"
						:list-items="customPropertyNames"
						:drop-zone="`custom-props-${schemaObject.id}-dz`"
						:show-handle="!readonly"
						:disabled="readonly"
						content-class="flex flex-nowrap items-start"
						handle-class="py-4 px-1"
						@update:list-items="items => onListPositionChange(items)"
					>
						<SchemaProperty
							:readonly="readonly"
							:model-value="objectProperties[name]"
							:name="name"
							:selectable="selectable"
							:sub-selection="subSelection?.children && subSelection.children[name]"
							class="my-2 ml-1"
							@update:sub-selection="selection => changeChildSelection(name, selection)"
							@update="input => onUpdateProperty(name, input.name, input.property)"
							@remove="onRemoveProperty(name)"
						/>
					</ListItemDraggable>
				</ListTransition>
				<div v-if="!readonly" class="flex items-center flex-nowrap pl-5 mt-2">
					<div class="flex-grow">
						<QBtn class="bg-green-900 text-sm" @click="onAddProperty('string', 'prop')">
							<AddPropertyIcon class="w-3" />
						</QBtn>
					</div>
					<div>
						<QBtn class="bg-green-900 text-sm" @click="onAddProperty('object', 'object')">
							<AddObjectIcon class="w-3" />
						</QBtn>
					</div>
				</div>
			</div>
		</div>
		<div v-if="!readonly || childObjectNames.length > 0" class="child-objects ml-4">
			<ListTransition
				name="fade-down-list"
				:data-drop-zone="`child-objects-${schemaObject.id}-dz`"
			>
				<ListItemDraggable
					v-for="(name, index) in childObjectNames"
					:key="`property-${objectProperties[name].id}`"
					:list-items="childObjectNames"
					:drop-zone="`child-objects-${schemaObject.id}-dz`"
					show-handle
					:disabled="readonly"
					content-class="flex flex-nowrap items-start"
					handle-class="py-4 px-2"
					:class="{'mb-8': index < childObjectNames.length - 1}"
					@update:list-items="items => onListPositionChange(items)"
				>
					<SchemaObject
						:readonly="readonly"
						:model-value="objectProperties[name]"
						:relation-name="name"
						:selectable="selectable"
						:sub-selection="subSelection?.children && subSelection.children[name]"
						hide-header
						@update:sub-selection="selection => changeChildSelection(name, selection)"
						@update:model-value="input => onUpdateProperty(name, name, input)"
					>
						<template #header>
							<SchemaProperty
								:readonly="readonly"
								:model-value="objectProperties[name]"
								:name="name"
								@update="input => onUpdateProperty(name, input.name, input.property)"
								@remove="onRemoveProperty(name)"
							/>
						</template>
					</SchemaObject>
				</ListItemDraggable>
			</ListTransition>
		</div>
	</div>
</template>
<script setup lang="ts">
import SchemaProperty from "@/components/Modules/SchemaEditor/SchemaProperty";
import { useSubSelection } from "@/components/Modules/SchemaEditor/subSelection";
import { JsonSchema, SelectionSchema } from "@/types";
import { FaSolidArrowRight as AddObjectIcon, FaSolidPlus as AddPropertyIcon } from "danx-icon";
import { cloneDeep, EditableDiv, ListItemDraggable, ListTransition } from "quasar-ui-danx";
import { computed, ref, watch } from "vue";

const props = withDefaults(defineProps<{
	hideHeader?: boolean;
	readonly?: boolean;
	relationName?: string;
	selectable?: boolean;
}>(), {
	relationName: "root"
});
const schemaObject = defineModel<JsonSchema>();
const subSelection = defineModel<SelectionSchema | null>("subSelection");
const objectProperties = ref(cloneDeep(schemaObject.value.properties || schemaObject.value.items?.properties || {}));

watch(() => schemaObject.value, (value) => {
	objectProperties.value = cloneDeep(value.properties || value.items?.properties || {});
});

const sortedPropertyNames = computed(() => Object.keys(objectProperties.value).sort((a, b) => objectProperties.value[a].position - objectProperties.value[b].position));
const childObjectNames = computed(() => sortedPropertyNames.value.filter(p => p && ["array", "object"].includes(objectProperties.value[p].type)));
const customPropertyNames = computed(() => sortedPropertyNames.value.filter(p => p && !childObjectNames.value.includes(p)));

const {
	isSelected,
	changeSelection,
	changeChildSelection
} = useSubSelection(subSelection, schemaObject.value);

function onUpdate(input: Partial<JsonSchema>) {
	const newSchemaObject = { ...schemaObject.value, ...input };
	if (JSON.stringify(newSchemaObject) === JSON.stringify(schemaObject.value)) {
		return;
	}

	schemaObject.value = newSchemaObject;
}

function onUpdateProperty(originalName, newName, input) {
	// If newName is set, use that as the current name and update the object property
	if (newName) {
		objectProperties.value[newName] = input || (originalName && objectProperties.value[originalName]) || null;
	}

	// If originalName is set and newName does not match, this is either a rename operation or a delete operation if newName is not set.
	// Either way, we need to remove the original property
	if (originalName && originalName !== newName) {
		delete objectProperties.value[originalName];
	}

	setPropertyIdsAndPositions();
	const properties = { ...objectProperties.value };
	let newSchemaObject;

	if (schemaObject.value.type === "array") {
		newSchemaObject = { ...schemaObject.value, items: { ...schemaObject.value.items, properties } };
	} else {
		newSchemaObject = { ...schemaObject.value, properties };
	}

	// Do not update if the schema object has not changed
	if (JSON.stringify(newSchemaObject) === JSON.stringify(schemaObject.value)) {
		return;
	}

	schemaObject.value = newSchemaObject;
}

function onAddProperty(type: string, baseName: string) {
	let count = 1;

	let name = baseName;
	while (objectProperties.value[name]) {
		name = baseName + ("_" + count++);
	}
	const property = { type };
	onUpdateProperty(null, name, property);
}

function onRemoveProperty(name) {
	onUpdateProperty(name, null, null);
}

function setPropertyIdsAndPositions() {
	let id = 0;
	let position = 0;
	for (let name of sortedPropertyNames.value) {
		if (!objectProperties.value[name].id) {
			objectProperties.value[name].id = id++;
		} else {
			id = Math.max(id, objectProperties.value[name].id + 1);
		}
		objectProperties.value[name].position = position++;
	}
}

function onListPositionChange(items) {
	let position = 0;
	for (let name of items) {
		objectProperties.value[name].position = position++;
	}

	// Just trigger the property update as we've already made the necessary changes
	onUpdateProperty(null, null, null);
}
</script>
