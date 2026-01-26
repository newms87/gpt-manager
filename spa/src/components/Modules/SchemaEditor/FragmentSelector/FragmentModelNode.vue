<template>
    <div class="fragment-model-node min-w-56 bg-slate-800 border border-slate-600 rounded-lg shadow-lg">
        <!-- Target Handles (always in DOM, hidden via opacity per VueFlow docs) -->
        <!-- Left handle for LR layout -->
        <Handle
            id="target-left"
            type="target"
            :position="Position.Left"
            :class="[
				'!bg-transparent !border-0',
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
				'!bg-transparent !border-0',
				{ '!opacity-0': data.path === 'root' || data.direction !== 'TB' }
			]"
        >
            <ArrayIndicatorDots v-if="isArray" :direction="data.direction" />
            <ObjectIndicatorDot v-else :direction="data.direction" />
        </Handle>

        <!-- Header -->
        <div class="flex items-center gap-2 bg-slate-700 px-3 py-2 rounded-t-lg">
            <QCheckbox
                :model-value="checkboxValue"
                :indeterminate-value="null"
                size="sm"
                color="sky"
                dark
                class="nodrag nopan"
                @update:model-value="onToggleAll"
            />
            <span class="font-bold text-slate-100 text-sm truncate flex-1">{{ data.name }}</span>
            <ShowHideButton
                v-model="modelDescriptionVisible"
                :show-icon="DescriptionIcon"
                :hide-icon="DescriptionIcon"
                size="sm"
                class="nodrag nopan flex-shrink-0"
                :class="modelDescription ? 'text-sky-600' : 'text-slate-500'"
            />
            <InfoDialog
                v-if="modelDescriptionVisible"
                @close="modelDescriptionVisible = false"
            >
                <template #title>
                    <span>{{ data.name }}</span>
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
        <div v-if="!isByModelMode || data.showProperties" class="properties-list">
            <div
                v-for="prop in displayProperties"
                :key="prop.name"
                class="flex items-center gap-2 px-3 py-1 hover:bg-slate-700/50 transition-colors"
                :class="{ 'bg-sky-900/30': !isByModelMode && isPropertySelected(prop.name) }"
            >
                <!-- Checkbox only shown in non-model-only mode -->
                <QCheckbox
                    v-if="!isByModelMode"
                    :model-value="isPropertySelected(prop.name)"
                    size="sm"
                    color="sky"
                    dark
                    class="nodrag nopan"
                    @update:model-value="onToggleProperty(prop.name)"
                />
                <component
                    :is="getTypeIcon(prop)"
                    class="w-3 text-slate-400 flex-shrink-0"
                />
                <span class="text-sm text-slate-200 truncate flex-1">{{ prop.title || prop.name }}</span>
                <ShowHideButton
                    v-model="descriptionVisibility[prop.name]"
                    :show-icon="DescriptionIcon"
                    :hide-icon="DescriptionIcon"
                    size="sm"
                    class="nodrag nopan flex-shrink-0"
                    :class="prop.description ? 'text-sky-600' : 'text-slate-500'"
                />
                <InfoDialog
                    v-if="descriptionVisibility[prop.name]"
                    @close="descriptionVisibility[prop.name] = false"
                >
                    <template #title>
                        <span>{{ prop.title || prop.name }}</span>
                    </template>
                    <MarkdownEditor
                        class="w-[70vw]"
                        :model-value="prop.description || ''"
                        readonly
                        hide-footer
                        min-height="60px"
                    />
                </InfoDialog>
            </div>
        </div>

        <!-- Source Handles (always in DOM, hidden via opacity per VueFlow docs) -->
        <SourceHandleDot position="right" :visible="hasModelChildren && data.direction === 'LR'" />
        <SourceHandleDot position="bottom" :visible="hasModelChildren && data.direction === 'TB'" />
    </div>
</template>

<script setup lang="ts">
import { Handle, Position } from "@vue-flow/core";
import {
    FaSolidCalendarDay as DateIcon,
    FaSolidClock as DateTimeIcon,
    FaSolidFileLines as DescriptionIcon,
    FaSolidFont as StringIcon,
    FaSolidHashtag as NumberIcon,
    FaSolidToggleOn as BooleanIcon
} from "danx-icon";
import { QCheckbox } from "quasar";
import { InfoDialog, MarkdownEditor, ShowHideButton } from "quasar-ui-danx";
import { computed, reactive, ref } from "vue";
import ArrayIndicatorDots from "./ArrayIndicatorDots.vue";
import ObjectIndicatorDot from "./ObjectIndicatorDot.vue";
import SourceHandleDot from "./SourceHandleDot.vue";
import { FragmentModelNodeData, PropertyInfo } from "./useFragmentSelectorGraph";

const props = defineProps<{
    data: FragmentModelNodeData;
}>();

const emit = defineEmits<{
    "toggle-property": [payload: { path: string; propertyName: string }];
    "toggle-all": [payload: { path: string; selectAll: boolean }];
}>();

const descriptionVisibility = reactive<Record<string, boolean>>({});
const modelDescriptionVisible = ref(false);

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

// In model-only mode, we check model properties; otherwise scalar properties
const selectableProperties = computed(() => {
    if (isByModelMode.value) {
        return props.data.properties.filter(p => p.isModel);
    }
    return displayProperties.value;
});

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

function getTypeIcon(prop: PropertyInfo) {
    if (prop.format === "date") return DateIcon;
    if (prop.format === "date-time") return DateTimeIcon;
    switch (prop.type) {
        case "string":
            return StringIcon;
        case "number":
            return NumberIcon;
        case "boolean":
            return BooleanIcon;
        default:
            return StringIcon;
    }
}
</script>
