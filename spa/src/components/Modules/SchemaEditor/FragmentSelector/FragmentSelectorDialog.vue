<template>
	<QDialog :model-value="isShowing" maximized @update:model-value="onClose">
		<div class="w-full h-full flex items-center justify-center" @click.self="onClose">
			<div class="w-[80vw] h-[80vh] bg-slate-900 rounded-lg flex flex-col">
				<!-- Header -->
				<div class="bg-slate-800 px-4 py-3 flex items-center justify-between rounded-t-lg">
					<span class="text-base font-semibold text-slate-100">Fragment Selector</span>
					<ActionButton
						type="cancel"
						color="slate"
						size="sm"
						tooltip="Close"
						@click="onClose"
					/>
				</div>

				<!-- Canvas -->
				<div class="flex-1 overflow-hidden">
					<FragmentSelectorCanvas
						:schema="schema"
						:model-value="localSelector"
						:selection-mode="selectionMode"
						:recursive="recursive"
						:type-filter="typeFilter"
						@update:model-value="localSelector = $event"
					/>
				</div>

				<!-- Footer -->
				<div class="bg-slate-800 px-4 py-3 flex items-center justify-between rounded-b-lg">
					<span class="text-xs text-slate-400">
						{{ modelCount }} {{ modelCount === 1 ? "model" : "models" }},
						{{ propertyCount }} {{ propertyCount === 1 ? "property" : "properties" }} selected
					</span>
					<div class="flex items-center gap-2">
						<ActionButton
							type="cancel"
							label="Cancel"
							color="slate"
							size="sm"
							@click="onClose"
						/>
						<ActionButton
							type="confirm"
							label="Apply"
							color="sky"
							size="sm"
							@click="onApply"
						/>
					</div>
				</div>
			</div>
		</div>
	</QDialog>
</template>

<script setup lang="ts">
import FragmentSelectorCanvas from "./FragmentSelectorCanvas.vue";
import { countModels, countProperties } from "./fragmentSelectorStats";
import { FragmentSelector, JsonSchema, JsonSchemaType } from "@/types";
import { ActionButton } from "quasar-ui-danx";
import { QDialog } from "quasar";
import { computed, ref, watch } from "vue";

const props = withDefaults(defineProps<{
	schema: JsonSchema;
	modelValue: FragmentSelector | null;
	selectionMode?: "by-model" | "by-property";
	recursive?: boolean;
	typeFilter?: JsonSchemaType | null;
}>(), {
	selectionMode: "by-property",
	recursive: true,
	typeFilter: null
});

const emit = defineEmits<{
	"update:modelValue": [value: FragmentSelector | null];
}>();

const isShowing = defineModel<boolean>("showing", { default: false });

// Local copy of the selector - only emitted on Apply
const localSelector = ref<FragmentSelector | null>(null);

// Sync local selector when dialog opens or modelValue changes
watch(() => props.modelValue, (newVal) => {
	localSelector.value = newVal ? JSON.parse(JSON.stringify(newVal)) : null;
}, { immediate: true });

// Stats: count models and properties in the local selector
const modelCount = computed(() => countModels(localSelector.value));
const propertyCount = computed(() => countProperties(localSelector.value));

function onApply(): void {
	emit("update:modelValue", localSelector.value);
	isShowing.value = false;
}

function onClose(): void {
	// Reset local selector to the original value
	localSelector.value = props.modelValue ? JSON.parse(JSON.stringify(props.modelValue)) : null;
	isShowing.value = false;
}
</script>
