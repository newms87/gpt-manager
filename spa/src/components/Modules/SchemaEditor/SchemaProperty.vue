<template>
	<div class="schema-property">
		<div class="flex items-center flex-nowrap group">
			<div class="flex items-center flex-nowrap flex-grow">
				<SchemaPropertyTypeMenu :readonly="readonly" :property="property" class="mr-2" @update="onUpdate" />
				<EditableDiv
					:readonly="readonly"
					:model-value="property.items?.title || property.title || name"
					color="slate-600"
					placeholder="Enter Property Name..."
					@update:model-value="title => onUpdate({title})"
				/>
			</div>
			<QBtn v-if="!readonly" class="group-hover:opacity-100 opacity-0 transition-all" @click="$emit('remove')">
				<RemoveIcon class="w-3 text-red-300" />
			</QBtn>
		</div>
		<div v-if="!readonly || !!descriptionText" class="ml-9">
			<EditableDiv
				:readonly="readonly"
				:model-value="descriptionText"
				color="slate-600"
				class="text-slate-400"
				placeholder="Enter description..."
				@update:model-value="description => onUpdate({description})"
			/>
		</div>
	</div>
</template>
<script setup lang="ts">
import SchemaPropertyTypeMenu from "@/components/Modules/SchemaEditor/SchemaPropertyTypeMenu";
import { JsonSchema } from "@/types";
import { FaSolidTrash as RemoveIcon } from "danx-icon";
import { EditableDiv } from "quasar-ui-danx";
import { computed } from "vue";

const emit = defineEmits(["update", "remove"]);
const property = defineModel<JsonSchema>();
const props = defineProps<{ name: string, readonly?: boolean }>();

const descriptionText = computed(() => property.value.items?.description || property.value.description || "");

function onUpdate(input: Partial<JsonSchema>) {
	const type = input.type || property.value.type;
	let name = props.name;
	const properties = property.value.items?.properties || property.value.properties || {};
	const object = {
		title: input.title || property.value.items?.title || property.value.title || "",
		description: input.description || property.value.items?.description || property.value.description || "",
		type: "object",
		properties
	};

	// Transform from object to array
	if (type === "array") {
		property.value = {
			id: property.value.id,
			position: property.value.position,
			type: "array",
			items: object
		};
	} else if (type === "object") {
		// Transform from array to object
		property.value = {
			id: property.value.id,
			position: property.value.position,
			...object
		};
	} else {
		// Standard update for all other types
		property.value = { ...property.value, ...input };
	}

	if (input.title) {
		name = slugName(input.title);
	}

	emit("update", { name, property: property.value });
}

function slugName(name: string) {
	return name.toLowerCase().replace(/[^a-z0-9]/g, "_");
}
</script>
