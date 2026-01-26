<template>
    <div class="fragment-model-node min-w-56 relative">
        <div class="bg-slate-800 border border-slate-600 rounded-lg shadow-lg">
        <!-- Target Handles (always in DOM, hidden via opacity per VueFlow docs) -->
        <!-- Left handle for LR layout -->
        <Handle
            id="target-left"
            type="target"
            :position="Position.Left"
            :class="[
				'!bg-transparent !border-0 z-20',
				{ '!opacity-0': data.path === 'root' || data.direction !== 'LR' }
			]"
        >
            <ArrayIndicatorDots v-if="isArray" :direction="data.direction" />
            <ObjectIndicatorDot v-else :direction="data.direction" />
        </Handle>
        <!-- Top handle for TB layout -->
        <Handle
            id="target-top"
            type="target"
            :position="Position.Top"
            :class="[
				'!bg-transparent !border-0 z-20',
				{ '!opacity-0': data.path === 'root' || data.direction !== 'TB' }
			]"
        >
            <ArrayIndicatorDots v-if="isArray" :direction="data.direction" />
            <ObjectIndicatorDot v-else :direction="data.direction" />
        </Handle>

        <!-- Header -->
        <div class="relative flex items-center gap-2 bg-slate-700 px-3 py-2 rounded-t-lg group nodrag nopan">
            <!-- DELETE BUTTON: Container extends from header edge to button, no gap -->
            <div
                v-if="data.editEnabled && data.path !== 'root'"
                class="absolute right-0 top-0 bottom-0 flex items-center pl-4 pr-2 -mr-10 opacity-0 group-hover:opacity-100 transition-opacity z-10"
            >
                <ActionButton
                    type="trash"
                    color="red"
                    size="xs"
                    @click="emit('remove-model', { path: data.path })"
                />
            </div>
            <!-- LEFT ELEMENT: Checkbox (select) / Spacer (edit & readonly) -->
            <div class="w-5 flex-shrink-0 flex items-center justify-center">
                <Transition name="fade" mode="out-in">
                    <QCheckbox
                        v-if="data.selectionEnabled"
                        key="checkbox"
                        :model-value="checkboxValue"
                        :indeterminate-value="null"
                        size="sm"
                        color="sky"
                        dark
                        @update:model-value="onToggleAll"
                    />
                    <div v-else key="spacer" class="w-4 h-4" />
                </Transition>
            </div>

            <!-- TITLE: EditableDiv - readonly when not editing -->
            <EditableDiv
                ref="titleInputRef"
                :model-value="data.schema.title || data.name"
                :readonly="!data.editEnabled"
                placeholder="Enter name..."
                color="slate-600"
                class="font-bold text-sm text-slate-100 flex-1"
                @update:model-value="title => emit('update-model', { path: data.path, updates: { title } })"
            />

            <!-- DESCRIPTION BUTTON -->
            <ShowHideButton
                v-model="modelDescriptionVisible"
                :show-icon="DescriptionIcon"
                :hide-icon="DescriptionIcon"
                size="sm"
                class="flex-shrink-0"
                :class="modelDescription ? 'text-sky-600' : 'text-slate-500'"
            />
            <InfoDialog
                v-if="modelDescriptionVisible"
                @close="modelDescriptionVisible = false"
            >
                <template #title>
                    <span>{{ data.schema.title || data.name }}</span>
                </template>
                <MarkdownEditor
                    class="w-[70vw]"
                    :model-value="modelDescription || ''"
                    readonly
                    hide-footer
                    min-height="60px"
                />
            </InfoDialog>
        </div>

        <!-- Properties List (non-model properties only, sorted by position) -->
        <!-- In model-only mode, only shown when showProperties is true (view-only, no checkboxes) -->
        <!-- In edit mode, always show properties for editing -->
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

        <!-- Footer: Add new property (only when editEnabled) -->
        <div
            v-if="data.editEnabled"
            class="flex items-center gap-2 px-3 py-3 border-t border-slate-600 cursor-pointer hover:bg-slate-700/50 transition-colors nodrag nopan"
            @click="emit('add-property', { path: data.path, type: 'string', baseName: 'prop' })"
        >
            <PlusIcon class="w-3 h-3 text-green-500" />
            <span class="text-xs text-green-500">New Property</span>
        </div>

        <!-- Source Handles: Connection indicators only (no interactive elements inside handles) -->
        <!-- Only show the handle that matches the current layout direction -->
        <Handle
            v-for="handle in sourceHandles"
            :id="handle.id"
            :key="handle.id"
            type="source"
            :position="handle.position"
            class="!bg-transparent !border-0"
            :class="{ '!opacity-0': data.direction !== handle.direction || (!data.editEnabled && !hasModelChildren) }"
        >
            <ObjectIndicatorDot v-if="data.direction === handle.direction && hasModelChildren && !data.editEnabled" :direction="data.direction" />
        </Handle>
        </div>

        <!-- Add Model Button: Positioned outside Handle to ensure proper click handling -->
        <div
            v-if="data.editEnabled"
            class="absolute z-20 pointer-events-auto nodrag nopan"
            :class="addModelButtonPosition"
        >
            <div
                class="w-6 h-6 bg-green-600 hover:bg-green-500 rounded-full flex items-center justify-center cursor-pointer transition-colors"
                @click.stop="onAddChildModel"
            >
                <PlusIcon class="w-3 h-3 text-white" />
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { JsonSchema } from "@/types";
import { Handle, Position } from "@vue-flow/core";
import { FaSolidFileLines as DescriptionIcon, FaSolidPlus as PlusIcon } from "danx-icon";
import { QCheckbox } from "quasar";
import { ActionButton, EditableDiv, InfoDialog, MarkdownEditor, ShowHideButton } from "quasar-ui-danx";
import { computed, ref, Transition, watch } from "vue";
import ArrayIndicatorDots from "./ArrayIndicatorDots.vue";
import FragmentPropertyRow from "./FragmentPropertyRow.vue";
import ObjectIndicatorDot from "./ObjectIndicatorDot.vue";
import { FragmentModelNodeData, getSchemaProperties, LayoutDirection } from "./useFragmentSelectorGraph";

// Source handle configurations for both layout directions
const sourceHandles: Array<{ id: string; position: typeof Position.Right | typeof Position.Bottom; direction: LayoutDirection }> = [
    { id: "source-right", position: Position.Right, direction: "LR" },
    { id: "source-bottom", position: Position.Bottom, direction: "TB" }
];

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

const modelDescriptionVisible = ref(false);
const titleInputRef = ref<InstanceType<typeof EditableDiv> | null>(null);

// Watch for focus trigger from parent (when new model is created)
watch(() => props.data.shouldFocus, (shouldFocus) => {
    if (shouldFocus && titleInputRef.value) {
        titleInputRef.value.focus(true); // Select all text when focusing
    }
});

const modelDescription = computed(() => {
    return props.data.schema?.items?.description || props.data.schema?.description;
});

const isArray = computed(() => {
    return props.data.schema?.type === "array";
});

const hasModelChildren = computed(() => {
    return props.data.properties.some(p => p.isModel);
});

// Compute position classes for the Add Model button based on layout direction
const addModelButtonPosition = computed(() => {
    if (props.data.direction === "LR") {
        // Right edge, vertically centered
        return "right-0 top-1/2 -translate-y-1/2 translate-x-1/2";
    } else {
        // Bottom edge, horizontally centered
        return "bottom-0 left-1/2 -translate-x-1/2 translate-y-1/2";
    }
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

/**
 * Get the JsonSchema for a property by name from the schema
 */
function getPropertySchema(name: string): JsonSchema {
    const properties = getSchemaProperties(props.data.schema);
    return properties?.[name] || { type: "string" };
}

const isAllSelected = computed(() => {
    // In model-only mode, check if this node is included in selection
    if (isByModelMode.value) {
        return props.data.isIncluded;
    }
    // Use the rollup state which considers all descendants
    return props.data.isFullySelected;
});

const isIndeterminate = computed(() => {
    // In model-only mode, no indeterminate state
    if (isByModelMode.value) {
        return false;
    }
    // Has some selection but not fully selected (ternary: partial state)
    return props.data.hasAnySelection && !props.data.isFullySelected;
});

// Computed value for QCheckbox that returns null for indeterminate state
const checkboxValue = computed(() => {
    if (isByModelMode.value) {
        return props.data.isIncluded;
    }
    if (isIndeterminate.value) {
        return null; // Indeterminate state
    }
    return props.data.isFullySelected;
});

function isPropertySelected(name: string): boolean {
    return props.data.selectedProperties.includes(name);
}

function onToggleProperty(propertyName: string) {
    emit("toggle-property", { path: props.data.path, propertyName });
}

function onToggleAll() {
    // If fully selected, deselect; otherwise select all (fills in remaining)
    emit("toggle-all", { path: props.data.path, selectAll: !isAllSelected.value });
}

function onAddChildModel() {
    emit("add-child-model", { path: props.data.path, type: "object", baseName: "model" });
}
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.15s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
