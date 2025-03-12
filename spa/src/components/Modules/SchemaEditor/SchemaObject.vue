<template>
	<div class="schema-object flex items-start flex-nowrap">
		<div
			class="parent-object bg-slate-700 rounded-lg overflow-hidden inline-block w-96 flex-shrink-0"
			:class="(selectable && readonly) ? (isSelected ? selectedClass : notSelectedClass) : ''"
		>
			<div class="flex items-start flex-nowrap px-4 py-2 bg-slate-800">
				<QCheckbox
					v-if="selectable"
					dense
					:model-value="isSelected"
					class="mr-2 py-1"
					@update:model-value="changeSelection"
				/>
				<div class="flex-grow">
					<slot name="header" :inline-description="showInlineDescriptions">
						<div class="py-1">
							<EditableDiv
								:readonly="readonly"
								:model-value="schemaObject.title || ''"
								color="slate-600"
								class="min-w-20"
								placeholder="(Enter Title)"
								:debounce-delay="1000"
								@update:model-value="title => onUpdate({title})"
							/>
						</div>
					</slot>
				</div>
				<div class="ml-2">
					<ShowHideButton v-model="showInlineDescriptions" size="xs" icon-class="w-4" />
				</div>
			</div>
			<div v-if="!readonly || customPropertyNames.length > 0" class="py-2 px-4">
				<ListTransition
					name="fade-down-list"
					:data-drop-zone="`custom-props-${schemaObject.id}-dz`"
				>
					<template
						v-for="name in customPropertyNames"
						:key="`property-${objectProperties[name].id}`"
					>
						<ListItemDraggable
							v-if="selectable || !readonly || !fragmentSelector || (fragmentSelector?.children && fragmentSelector?.children[name])"
							:list-items="customPropertyNames"
							:drop-zone="`custom-props-${schemaObject.id}-dz`"
							:show-handle="!readonly"
							:disabled="readonly"
							content-class="flex flex-nowrap items-start"
							handle-class="py-4 px-1"
							@update:list-items="items => onListPositionChange(items)"
						>
							<SchemaProperty
								v-model:inline-description="showInlineDescriptions"
								:readonly="readonly"
								:model-value="objectProperties[name]"
								:name="name"
								:selectable="selectable"
								:fragment-selector="fragmentSelector?.children && fragmentSelector.children[name]"
								class="my-2 ml-1"
								@update:fragment-selector="selection => changeChildSelection(name, schemaObject.type, selection)"
								@update="input => onUpdateProperty(name, input.name, input.property)"
								@remove="onRemoveProperty(name)"
							/>
						</ListItemDraggable>
					</template>
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
				<template
					v-for="(name, index) in childObjectNames"
					:key="`property-${objectProperties[name].id}`"
				>
					<ListItemDraggable
						v-if="selectable || !readonly || !fragmentSelector || (fragmentSelector?.children && fragmentSelector.children[name])"
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
							:inline-descriptions="showInlineDescriptions"
							:readonly="readonly"
							:model-value="objectProperties[name]"
							:relation-name="name"
							:selectable="selectable"
							:fragment-selector="fragmentSelector?.children && fragmentSelector.children[name]"
							hide-header
							@update:fragment-selector="selection => changeChildSelection(name, schemaObject.type, selection)"
							@update:model-value="input => onUpdateProperty(name, name, input)"
						>
							<template #header="{inlineDescription}">
								<SchemaProperty
									:inline-description="inlineDescription || showInlineDescriptions"
									:readonly="readonly"
									:model-value="objectProperties[name]"
									:name="name"
									@update="input => onUpdateProperty(name, input.name, input.property)"
									@remove="onRemoveProperty(name)"
								/>
							</template>
						</SchemaObject>
					</ListItemDraggable>
				</template>
			</ListTransition>
		</div>
	</div>
</template>
<script setup lang="ts">
import { useFragmentSelector } from "@/components/Modules/SchemaEditor/fragmentSelector";
import SchemaProperty from "@/components/Modules/SchemaEditor/SchemaProperty";
import { FragmentSelector, JsonSchema } from "@/types";
import { FaSolidArrowRight as AddObjectIcon, FaSolidPlus as AddPropertyIcon } from "danx-icon";
import { cloneDeep, EditableDiv, ListItemDraggable, ListTransition, ShowHideButton } from "quasar-ui-danx";
import { computed, ref, watch } from "vue";

withDefaults(defineProps<{
	hideHeader?: boolean;
	readonly?: boolean;
	relationName?: string;
	selectable?: boolean;
	selectedClass?: string;
	notSelectedClass?: string;
}>(), {
	relationName: "root",
	selectedClass: "",
	notSelectedClass: "opacity-50"
});
const schemaObject = defineModel<JsonSchema>();
const fragmentSelector = defineModel<FragmentSelector | null>("fragmentSelector");
const showInlineDescriptions = defineModel<boolean>("inlineDescriptions");
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
} = useFragmentSelector(fragmentSelector, schemaObject.value);

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
		newSchemaObject = { ...schemaObject.value, items: { ...schemaObject.value.items, properties, type: "object" } };
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
