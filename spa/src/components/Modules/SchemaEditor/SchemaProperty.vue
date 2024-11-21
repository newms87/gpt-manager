<template>
	<div class="flex items-center flex-nowrap">
		<SchemaPropertyTypeMenu :property="property" class="mr-2" @update="onUpdate" />
		<EditableDiv
			v-model="name"
			color="slate-600"
			class="text-xs"
			@update:model-value="onUpdate({})"
		/>
		<div class="font-bold text-xs mr-1">:</div>
		<EditableDiv
			:model-value="property.title || ''"
			color="slate-600"
			placeholder="Enter Property Name..."
			@update:model-value="title => onUpdate({title})"
		/>
	</div>
</template>
<script setup lang="ts">
import SchemaPropertyTypeMenu from "@/components/Modules/SchemaEditor/SchemaPropertyTypeMenu";
import { JsonSchema } from "@/types";
import { EditableDiv } from "quasar-ui-danx";

const emit = defineEmits(["update"]);
const property = defineModel<JsonSchema>();
const name = defineModel<string>("name");

function onUpdate(input: Partial<JsonSchema>) {
	property.value = { ...property.value, ...input };
	emit("update", { name: name.value, property: property.value });
}
</script>
