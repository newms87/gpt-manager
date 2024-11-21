<template>
	<div class="bg-slate-700 rounded-lg inline-block">
		<div class="flex items-center flex-nowrap px-4 py-2 bg-slate-800">
			<EditableDiv
				:model-value="schemaObject.title"
				color="slate-600"
				@update:model-value="title => onUpdateDebounced({title})"
			/>
		</div>
		<div class="py-2 px-4">
			<SchemaProperty
				v-for="name in customPropertyNames"
				:key="`property-${name}`"
				:model-value="schemaObject.properties[name]"
				:name="name"
				class="my-2"
				@update="input => onUpdateProperty(name, input)"
			/>
		</div>
	</div>
</template>
<script setup lang="ts">
import SchemaProperty from "@/components/Modules/SchemaEditor/SchemaProperty";
import { JsonSchema } from "@/types";
import { useDebounceFn } from "@vueuse/core";
import { EditableDiv } from "quasar-ui-danx";
import { computed } from "vue";

const schemaObject = defineModel<JsonSchema>();

function onUpdate(input: Partial<JsonSchema>) {
	schemaObject.value = { ...schemaObject.value, properties: { ...schemaObject.value.properties, ...input } };
	console.log("updated property", input, schemaObject.value);
}
const onUpdateDebounced = useDebounceFn(onUpdate, 500);

function onUpdateProperty(propertyName, input) {
	console.log("updating property", propertyName, input);
	if (propertyName !== input.name) {
		delete schemaObject.value.properties[propertyName];
	}
	onUpdate({ [input.name]: input.property });
}
const customPropertyNames = computed(() => Object.keys(schemaObject.value.properties).filter(p => p && !["name", "date", "description"].includes(p)));
</script>
