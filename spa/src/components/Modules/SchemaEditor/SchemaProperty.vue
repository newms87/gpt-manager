<template>
	<div class="flex items-center flex-nowrap group">
		<div class="flex items-center flex-nowrap flex-grow">
			<SchemaPropertyTypeMenu :property="property" class="mr-2" @update="onUpdate" />
			<EditableDiv
				v-model="name"
				color="slate-600"
				class="text-xs"
				@update:model-value="onUpdate({})"
			/>
			<div class="font-bold text-xs mr-1">:</div>
			<EditableDiv
				:model-value="property.items?.title || property.title || ''"
				color="slate-600"
				placeholder="Enter Property Name..."
				@update:model-value="title => onUpdate({title})"
			/>
		</div>
		<QBtn class="group-hover:opacity-100 opacity-0 transition-all" @click="$emit('remove')">
			<RemoveIcon class="w-3 text-red-300" />
		</QBtn>
	</div>
</template>
<script setup lang="ts">
import SchemaPropertyTypeMenu from "@/components/Modules/SchemaEditor/SchemaPropertyTypeMenu";
import { JsonSchema } from "@/types";
import { FaSolidTrash as RemoveIcon } from "danx-icon";
import { EditableDiv } from "quasar-ui-danx";

const emit = defineEmits(["update", "remove"]);
const property = defineModel<JsonSchema>();
const name = defineModel<string>("name");

function onUpdate(input: Partial<JsonSchema>) {
	const type = input.type || property.value.type;

	// Transform from object to array
	if (type === "array") {
		property.value = {
			id: property.value.id,
			position: property.value.position,
			type: "array",
			items: {
				title: input.title || property.value.title || "",
				type: "object",
				properties: {}
			}
		};
	} else if (type === "object") {
		// Transform from array to object
		property.value = {
			id: property.value.id,
			position: property.value.position,
			title: input.title || property.value.items?.title || property.value.title || "",
			type: "object",
			properties: property.value.items?.properties || property.value.properties || {}
		};
	} else {
		// Standard update for all other types
		property.value = { ...property.value, ...input };
	}

	emit("update", { name: name.value, property: property.value });
}
</script>
