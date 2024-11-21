<template>
	<div class="schema-object flex items-start flex-nowrap">
		<div class="parent-object bg-slate-700 rounded-lg inline-block w-96">
			<div class="flex items-center flex-nowrap px-4 py-2 bg-slate-800">
				<slot name="header" />
				<EditableDiv
					v-if="!hideHeader"
					:model-value="schemaObject.title || ''"
					color="slate-600"
					class="min-w-20"
					placeholder="(Enter Title)"
					@update:model-value="title => onUpdateDebounced({title})"
				/>
			</div>
			<div class="py-2 px-4">
				<SchemaProperty
					v-for="name in customPropertyNames"
					:key="`property-${name}`"
					:model-value="properties[name]"
					:name="name"
					class="my-2"
					@update="input => onUpdateProperty(name, input)"
				/>
			</div>
		</div>
		<div class="child-objects ml-4">
			<div
				v-for="name in childObjectNames"
				:key="`property-${name}`"
				class="mb-4"
			>
				<SchemaObject
					:model-value="properties[name]"
					hide-header
					@update:model-value="input => onUpdateObject(name, input)"
				>
					<template #header>
						<SchemaProperty
							:model-value="properties[name]"
							:name="name"
							@update="input => onUpdateProperty(name, input)"
						/>
					</template>
				</SchemaObject>
			</div>
		</div>
	</div>
</template>
<script setup lang="ts">
import SchemaProperty from "@/components/Modules/SchemaEditor/SchemaProperty";
import { JsonSchema } from "@/types";
import { useDebounceFn } from "@vueuse/core";
import { EditableDiv } from "quasar-ui-danx";
import { computed } from "vue";

defineProps<{
	hideHeader?: boolean
}>();
const schemaObject = defineModel<JsonSchema>();

function onUpdate(input: Partial<JsonSchema>) {
	if (schemaObject.value.type === "array") {
		schemaObject.value = {
			...schemaObject.value,
			items: { ...schemaObject.value.items, properties: { ...schemaObject.value.items.properties, ...input } }
		};
	} else {
		schemaObject.value = {
			...schemaObject.value,
			properties: { ...schemaObject.value.properties, ...input }
		};
	}
}
const onUpdateDebounced = useDebounceFn(onUpdate, 500);

function onUpdateObject(name, input) {
	onUpdate({ [name]: input });
}

function onUpdateProperty(propertyName, input) {
	if (propertyName !== input.name) {
		delete schemaObject.value.properties[propertyName];
	}
	onUpdate({ [input.name]: input.property });
}
const properties = computed(() => schemaObject.value.properties || schemaObject.value.items?.properties || {});
const childObjectNames = computed(() => Object.keys(properties.value).filter(p => p && ["array", "object"].includes(properties.value[p].type)));
const customPropertyNames = computed(() => Object.keys(properties.value).filter(p => p && !["name", "date", "description", ...childObjectNames.value].includes(p)));
</script>
