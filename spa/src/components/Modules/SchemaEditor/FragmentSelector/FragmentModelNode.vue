<template>
	<div class="fragment-model-node min-w-56 relative">
		<div class="bg-slate-800 border border-slate-600 rounded-lg shadow-lg">
			<!-- Target Handles -->
			<FragmentModelNodeHandles
				type="target"
				:direction="data.direction"
				:is-array="isArray"
				:has-model-children="hasModelChildren"
				:edit-enabled="Boolean(data.editEnabled)"
				:is-root="data.path === 'root'"
			/>

			<!-- Header -->
			<FragmentModelNodeHeader
				:title="data.schema.title || data.name"
				:description="modelDescription"
				:edit-enabled="Boolean(data.editEnabled)"
				:selection-enabled="Boolean(data.selectionEnabled)"
				:is-root="data.path === 'root'"
				:checkbox-value="checkboxValue"
				:should-focus="data.shouldFocus"
				@toggle-all="onToggleAll"
				@update-model="title => emit('update-model', { path: data.path, updates: { title } })"
				@remove-model="emit('remove-model', { path: data.path })"
			/>

			<!-- Properties List (non-model properties only, sorted by position) -->
			<div v-if="data.editEnabled || !isByModelMode || data.showProperties" class="properties-list">
				<FragmentPropertyRow
					v-for="prop in displayProperties"
					:key="prop.name"
					:name="prop.name"
					:property="getPropertySchema(prop.name)"
					:edit-active="Boolean(data.editEnabled)"
					:selection-active="Boolean(data.selectionEnabled) && !isByModelMode"
					:is-selected="isPropertySelected(prop.name)"
					:show-description="true"
					@toggle="onToggleProperty(prop.name)"
					@update-name="newName => emit('update-property', { path: data.path, originalName: prop.name, newName, updates: {} })"
					@update-type="typeUpdate => emit('update-property', { path: data.path, originalName: prop.name, newName: prop.name, updates: typeUpdate })"
					@remove="emit('remove-property', { path: data.path, name: prop.name })"
				/>
			</div>

			<!-- Footer: Add new property -->
			<FragmentModelNodeFooter
				:edit-enabled="Boolean(data.editEnabled)"
				@add-property="emit('add-property', { path: data.path, type: 'string', baseName: 'prop' })"
			/>

			<!-- Source Handles -->
			<FragmentModelNodeHandles
				type="source"
				:direction="data.direction"
				:is-array="isArray"
				:has-model-children="hasModelChildren"
				:edit-enabled="Boolean(data.editEnabled)"
				:is-root="data.path === 'root'"
			/>
		</div>

		<!-- Add Model Button (outside main container) -->
		<FragmentModelNodeAddButton
			:edit-enabled="Boolean(data.editEnabled)"
			:direction="data.direction"
			@add-child-model="emit('add-child-model', { path: data.path, type: 'object', baseName: 'model' })"
		/>
	</div>
</template>

<script setup lang="ts">
import { JsonSchema } from "@/types";
import { computed } from "vue";
import FragmentModelNodeAddButton from "./FragmentModelNodeAddButton.vue";
import FragmentModelNodeFooter from "./FragmentModelNodeFooter.vue";
import FragmentModelNodeHandles from "./FragmentModelNodeHandles.vue";
import FragmentModelNodeHeader from "./FragmentModelNodeHeader.vue";
import FragmentPropertyRow from "./FragmentPropertyRow.vue";
import { FragmentModelNodeData } from "./types";
import { getSchemaProperties } from "./useSchemaNavigation";

const props = defineProps<{
	data: FragmentModelNodeData;
}>();

const emit = defineEmits<{
	"toggle-property": [payload: { path: string; propertyName: string }];
	"toggle-all": [payload: { path: string; selectAll: boolean }];
	"add-property": [payload: { path: string; type: string; baseName: string }];
	"update-property": [payload: { path: string; originalName: string; newName: string; updates: object }];
	"remove-property": [payload: { path: string; name: string }];
	"add-child-model": [payload: { path: string; type: "object" | "array"; baseName: string }];
	"update-model": [payload: { path: string; updates: object }];
	"remove-model": [payload: { path: string }];
}>();

const modelDescription = computed(() => {
	return props.data.schema?.items?.description || props.data.schema?.description;
});

const isArray = computed(() => {
	return props.data.schema?.type === "array";
});

const hasModelChildren = computed(() => {
	return props.data.properties.some(p => p.isModel);
});

const isByModelMode = computed(() => {
	return props.data.selectionMode === "by-model";
});

const displayProperties = computed(() => {
	return props.data.properties
		.filter(p => !p.isModel)
		.sort((a, b) => {
			if (a.position === undefined && b.position === undefined) return 0;
			if (a.position === undefined) return 1;
			if (b.position === undefined) return -1;
			return a.position - b.position;
		});
});

const isAllSelected = computed(() => {
	if (isByModelMode.value) {
		return props.data.isIncluded;
	}
	return props.data.isFullySelected;
});

const isIndeterminate = computed(() => {
	if (isByModelMode.value) {
		return false;
	}
	return props.data.hasAnySelection && !props.data.isFullySelected;
});

const checkboxValue = computed(() => {
	if (isByModelMode.value) {
		return props.data.isIncluded;
	}
	if (isIndeterminate.value) {
		return null;
	}
	return props.data.isFullySelected;
});

function getPropertySchema(name: string): JsonSchema {
	const properties = getSchemaProperties(props.data.schema);
	return properties?.[name] || { type: "string" };
}

function isPropertySelected(name: string): boolean {
	return props.data.selectedProperties.includes(name);
}

function onToggleProperty(propertyName: string) {
	emit("toggle-property", { path: props.data.path, propertyName });
}

function onToggleAll() {
	emit("toggle-all", { path: props.data.path, selectAll: !isAllSelected.value });
}
</script>
