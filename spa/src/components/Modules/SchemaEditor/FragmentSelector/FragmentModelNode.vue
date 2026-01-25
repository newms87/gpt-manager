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
            <!-- Triple dot for array, single dot for object -->
            <div v-if="isArray" class="relative w-4 h-4 -mt-[.3rem] -ml-2">
                <div class="absolute top-0 right-0 w-1.5 h-1.5 rounded-full bg-amber-400" />
                <div class="absolute top-[.35rem] left-0 w-1.5 h-1.5 rounded-full bg-amber-400" />
                <div class="absolute bottom-0 right-0 w-1.5 h-1.5 rounded-full bg-amber-400" />
            </div>
            <div v-else class="w-2.5 h-2.5 -mt-[0.15rem] -ml-[.1rem] rounded-full bg-sky-400" />
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
            <!-- Triple dot for array, single dot for object -->
            <div v-if="isArray" class="relative w-4 h-4 -mt-2 -ml-[.33rem]">
                <div class="absolute top-0 left-[50%] -ml-[.1875rem] w-1.5 h-1.5 rounded-full bg-amber-400" />
                <div class="absolute bottom-0 left-0 w-1.5 h-1.5 rounded-full bg-amber-400" />
                <div class="absolute bottom-0 right-0 w-1.5 h-1.5 rounded-full bg-amber-400" />
            </div>
            <div v-else class="w-2.5 h-2.5 -mt-0.5 -ml-[.1rem] rounded-full bg-sky-400" />
        </Handle>

        <!-- Header -->
        <div class="flex items-center gap-2 bg-slate-700 px-3 py-2 rounded-t-lg">
            <QCheckbox
                :model-value="isAllSelected"
                :indeterminate-value="isIndeterminate ? true : undefined"
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
        <!-- In structure-only mode, only shown when showProperties is true (view-only, no checkboxes) -->
        <div v-if="!isStructureOnlyMode || data.showProperties" class="properties-list">
            <div
                v-for="prop in displayProperties"
                :key="prop.name"
                class="flex items-center gap-2 px-3 py-1 hover:bg-slate-700/50 transition-colors"
                :class="{ 'bg-sky-900/30': !isStructureOnlyMode && isPropertySelected(prop.name) }"
            >
                <!-- Checkbox only shown in non-structure-only mode -->
                <QCheckbox
                    v-if="!isStructureOnlyMode"
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
        <Handle
            id="source-right"
            type="source"
            :position="Position.Right"
            :class="['!bg-green-500 !w-2.5 !h-2.5 !border-green-300 !rounded-full', { '!opacity-0': !hasModelChildren || data.direction !== 'LR' }]"
        />
        <Handle
            id="source-bottom"
            type="source"
            :position="Position.Bottom"
            :class="['!bg-green-500 !w-2.5 !h-2.5 !border-green-300 !rounded-full', { '!opacity-0': !hasModelChildren || data.direction !== 'TB' }]"
        />
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

const isStructureOnlyMode = computed(() => {
    return props.data.selectionMode === "structure-only";
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

// In structure-only mode, we check model properties; otherwise scalar properties
const selectableProperties = computed(() => {
    if (isStructureOnlyMode.value) {
        return props.data.properties.filter(p => p.isModel);
    }
    return displayProperties.value;
});

const isAllSelected = computed(() => {
    // In structure-only mode, check if this node is included in selection
    if (isStructureOnlyMode.value) {
        return props.data.isIncluded;
    }
    if (selectableProperties.value.length === 0) return false;
    const selectableNames = selectableProperties.value.map(p => p.name);
    const selectedSelectable = props.data.selectedProperties.filter(name => selectableNames.includes(name));
    return selectedSelectable.length === selectableNames.length;
});

const isIndeterminate = computed(() => {
    // In structure-only mode, no indeterminate state
    if (isStructureOnlyMode.value) {
        return false;
    }
    const selectableNames = selectableProperties.value.map(p => p.name);
    const selectedSelectable = props.data.selectedProperties.filter(name => selectableNames.includes(name));
    return selectedSelectable.length > 0 && selectedSelectable.length < selectableNames.length;
});

function isPropertySelected(name: string): boolean {
    return props.data.selectedProperties.includes(name);
}

function onToggleProperty(propertyName: string) {
    emit("toggle-property", { path: props.data.path, propertyName });
}

function onToggleAll() {
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
