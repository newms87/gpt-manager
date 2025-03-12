<template>
	<div class="schema-property">
		<div class="flex items-center flex-nowrap group">
			<QCheckbox
				v-if="selectable"
				dense
				:model-value="isSelected"
				class="mr-2 py-1"
				@update:model-value="changeSelection"
			/>
			<div class="flex items-center flex-nowrap flex-grow">
				<SchemaPropertyTypeMenu :readonly="readonly" :property="property" class="mr-2" @update="onUpdate" />
				<EditableDiv
					:readonly="readonly"
					:model-value="property.items?.title || property.title || name"
					color="slate-600"
					placeholder="Enter Property Name..."
					@update:model-value="title => onUpdate({title})"
				/>
				<ShowHideButton
					v-model="isViewingDescription"
					:show-icon="DescriptionIcon"
					size="xs"
					class="bg-transparent ml-2"
					:class="{[descriptionText ? 'text-sky-600' : 'text-slate-500']: true}"
				/>
				<InfoDialog v-if="isViewingDescription" :backdrop-dismiss="readonly" @close="isViewingDescription = false">
					<MarkdownEditor
						class="w-96 h-96"
						:readonly="readonly"
						:model-value="descriptionText"
						format="text"
						@update:model-value="description => onUpdate({description: description as string})"
					/>
				</InfoDialog>
			</div>
			<ActionButton
				v-if="!readonly"
				type="trash"
				class="group-hover:opacity-100 opacity-0 transition-all"
				color="red"
				size="xs"
				@click="$emit('remove')"
			/>
		</div>
		<div v-if="shouldShowInlineDescription" class="ml-9">
			<MarkdownEditor
				:readonly="readonly"
				:model-value="descriptionText"
				editor-class="text-slate-400 rounded w-full"
				format="text"
				@update:model-value="description => onUpdate({description: description as string})"
			/>
		</div>
	</div>
</template>
<script setup lang="ts">
import MarkdownEditor from "@/components/MarkdownEditor/MarkdownEditor";
import { useFragmentSelector } from "@/components/Modules/SchemaEditor/fragmentSelector";
import SchemaPropertyTypeMenu from "@/components/Modules/SchemaEditor/SchemaPropertyTypeMenu";
import { FragmentSelector, JsonSchema } from "@/types";
import { FaSolidFileLines as DescriptionIcon } from "danx-icon";
import { ActionButton, EditableDiv, InfoDialog, ShowHideButton } from "quasar-ui-danx";
import { computed, ref } from "vue";

const emit = defineEmits(["update", "remove"]);
const property = defineModel<JsonSchema>();
const fragmentSelector = defineModel<FragmentSelector | null>("fragmentSelector");
const showInlineDescription = defineModel<boolean>("inlineDescription");
const props = defineProps<{ name: string, readonly?: boolean, selectable?: boolean }>();

const descriptionText = computed(() => property.value.items?.description || property.value.description || "");
const isViewingDescription = ref(false);

/** If the inline description should be shown based on the toggle and if the description has text or is editable */
const shouldShowInlineDescription = computed(() => {
	if (!showInlineDescription.value) return false;

	// Show the description if there is a value or if we need to edit the value (weather or not there is a value)
	return descriptionText.value || !props.readonly;
});

const {
	isSelected,
	changeSelection
} = useFragmentSelector(fragmentSelector, property.value);

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
		} as JsonSchema;
	} else if (type === "object") {
		// Transform from array to object
		property.value = {
			id: property.value.id,
			position: property.value.position,
			...object
		} as JsonSchema;
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
