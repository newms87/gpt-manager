<template>
	<div class="schema-object flex items-start flex-nowrap">
		<div class="parent-object bg-slate-700 rounded-lg inline-block w-96">
			<div class="flex items-center flex-nowrap px-4 py-2 bg-slate-800">
				<div v-if="$slots.header" class="flex-grow">
					<slot name="header" />
				</div>
				<div class="py-1">
					<EditableDiv
						v-if="!hideHeader"
						:model-value="schemaObject.title || ''"
						color="slate-600"
						class="min-w-20"
						placeholder="(Enter Title)"
						@update:model-value="title => onUpdateDebounced({title})"
					/>
				</div>
			</div>
			<div class="py-2 px-4">
				<ListTransition
					name="fade-down-list"
					:data-drop-zone="`custom-props-${schemaObject.id}-dz`"
				>
					<ListItemDraggable
						v-for="name in customPropertyNames"
						:key="`property-${objectProperties[name].id || name}`"
						:list-items="customPropertyNames"
						:drop-zone="`custom-props-${schemaObject.id}-dz`"
						show-handle
						@update:list-items="items => onListPositionChange(items)"
					>
						<SchemaProperty
							:model-value="objectProperties[name]"
							:name="name"
							class="my-2 ml-1"
							@update="input => onUpdateProperty(name, input)"
							@remove="onRemoveProperty(name)"
						/>
					</ListItemDraggable>
				</ListTransition>
				<div class="flex items-center flex-nowrap pl-5 mt-2">
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
		<div class="child-objects ml-4">
			<ListTransition
				name="fade-down-list"
				:data-drop-zone="`child-objects-${schemaObject.id}-dz`"
			>
				<ListItemDraggable
					v-for="(name, index) in childObjectNames"
					:key="`property-${objectProperties[name].id || name}`"
					:list-items="childObjectNames"
					:drop-zone="`child-objects-${schemaObject.id}-dz`"
					show-handle
					content-class="flex flex-nowrap items-start"
					handle-class="py-4 px-2"
					:class="{'mb-8': index < childObjectNames.length - 1}"
					@update:list-items="items => onListPositionChange(items)"
				>
					<SchemaObject
						:model-value="objectProperties[name]"
						hide-header
						@update:model-value="input => onUpdateObject(name, input)"
					>
						<template #header>
							<SchemaProperty
								:model-value="objectProperties[name]"
								:name="name"
								@update="input => onUpdateProperty(name, input)"
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
import { JsonSchema } from "@/types";
import { useDebounceFn } from "@vueuse/core";
import { FaSolidArrowRight as AddObjectIcon, FaSolidPlus as AddPropertyIcon } from "danx-icon";
import { cloneDeep, EditableDiv, ListItemDraggable, ListTransition } from "quasar-ui-danx";
import { computed, ref, watch } from "vue";

defineProps<{
	hideHeader?: boolean
}>();
const schemaObject = defineModel<JsonSchema>();
const objectProperties = ref(cloneDeep(schemaObject.value.properties || schemaObject.value.items?.properties || {}));

watch(() => schemaObject.value, (value) => {
	objectProperties.value = cloneDeep(value.properties || value.items?.properties || {});
});

function onUpdate(input: Partial<JsonSchema>) {
	objectProperties.value = { ...objectProperties.value, ...input };
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
const onUpdateDebounced = useDebounceFn(onUpdate, 500);

function onUpdateObject(name, input) {
	onUpdate({ [name]: input });
}

function onUpdateProperty(propertyName, input) {
	if (propertyName !== input.name) {
		delete objectProperties.value[propertyName];
	}

	onUpdate({ [input.name]: input.property });
}

function onAddProperty(type: string, baseName: string) {
	let count = 1;

	let name = baseName;
	while (objectProperties.value[name]) {
		name = baseName + ("_" + count++);
	}
	const property = { type };
	onUpdate({ [name]: property });
}

function onRemoveProperty(name) {
	delete objectProperties.value[name];
	onUpdate({});
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

	onUpdate({});
}

const sortedPropertyNames = computed(() => Object.keys(objectProperties.value).sort((a, b) => objectProperties.value[a].position - objectProperties.value[b].position));
const childObjectNames = computed(() => sortedPropertyNames.value.filter(p => p && ["array", "object"].includes(objectProperties.value[p].type)));
const customPropertyNames = computed(() => sortedPropertyNames.value.filter(p => p && !childObjectNames.value.includes(p)));
</script>
