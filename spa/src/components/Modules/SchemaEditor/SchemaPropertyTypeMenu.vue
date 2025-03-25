<template>
	<QBtn class="bg-sky-800 rounded-lg">
		<Component :is="selectedTypeOption?.icon" class="w-3" />
		<QMenu v-if="!readonly" auto-close>
			<div
				v-for="type in allowedTypeOptions"
				:key="type.value"
				class="flex-x space-x-2 py-2 px-4 cursor-pointer hover:bg-slate-700"
				:class="{ 'bg-slate-600': isType(property, type) }"
				@click="onUpdate(type)"
			>
				<Component :is="type.icon" class="w-3 text-sky-500" />
				<div>{{ type.label }}</div>
			</div>
		</QMenu>
	</QBtn>
</template>
<script setup lang="ts">
import { JsonSchema } from "@/types";
import {
	FaSolidCalendarDay as DateIcon,
	FaSolidClock as DateTimeIcon,
	FaSolidFont as StringIcon,
	FaSolidHashtag as NumberIcon,
	FaSolidList as ArrayIcon,
	FaSolidObjectGroup as ObjectIcon,
	FaSolidToggleOn as BooleanIcon
} from "danx-icon";
import { computed } from "vue";

export interface PropertyTypeOption {
	value: string;
	label: string;
	icon: object;
	readonly?: boolean;
}

const emit = defineEmits(["update"]);
const props = defineProps<{ property: Partial<JsonSchema>, readonly?: boolean }>();

const selectedTypeOption = computed(() => typeOptions.find(type => isType(props.property, type)));

function isType(property: Partial<JsonSchema>, type: PropertyTypeOption) {
	switch (property.format) {
		case "date":
		case "date-time":
			return type.value === property.format;
	}

	return type.value === property.type;
}

function onUpdate(option: PropertyTypeOption) {
	let format: string = undefined;
	let type: string = option.value;

	switch (option.value) {
		case "date":
		case "date-time":
			format = type;
			type = "string";
			break;
	}

	emit("update", format ? { type, format } : { type });
}

const typeOptions: PropertyTypeOption[] = [
	{
		value: "object",
		label: "Single",
		icon: ObjectIcon
	},
	{
		value: "array",
		label: "Multiple",
		icon: ArrayIcon
	},
	{
		value: "string",
		label: "String",
		icon: StringIcon
	},
	{
		value: "number",
		label: "Number",
		icon: NumberIcon
	},
	{
		value: "boolean",
		label: "Boolean",
		icon: BooleanIcon
	},
	{
		value: "date",
		label: "Date",
		icon: DateIcon
	},
	{
		value: "date-time",
		label: "Date-Time",
		icon: DateTimeIcon
	}
];

const allowedTypeOptions = computed(() => {
	const objectTypes = ["object", "array"];
	if (objectTypes.includes(props.property.type)) {
		return typeOptions.filter(option => objectTypes.includes(option.value));
	}
	return typeOptions.filter(option => !objectTypes.includes(option.value));
});
</script>
