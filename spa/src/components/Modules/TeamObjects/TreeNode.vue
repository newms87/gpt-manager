<template>
    <div>
        <!-- Main node container -->
        <div
            :class="[
        'flex items-center gap-2 p-1.5 rounded cursor-pointer transition-all duration-200',
        isSelected
          ? 'bg-blue-600/30 border border-blue-500/50 text-blue-100'
          : 'hover:bg-slate-700 text-slate-300',
        level > 0 ? 'ml-4' : ''
      ]"
            @click="$emit('select', object)"
        >
            <!-- Expand/collapse button or spacer -->
            <button
                v-if="hasChildren"
                class="flex items-center justify-center w-4 h-4 rounded hover:bg-slate-600 transition-colors"
                @click.stop="$emit('toggle', object.id)"
            >
                <component
                    :is="ChevronRightIcon"
                    :class="[
            'w-3 h-3 text-slate-400 transition-transform',
            isExpanded ? 'transform rotate-90' : ''
          ]"
                />
            </button>
            <div v-else class="w-4"></div>

            <!-- Content area -->
            <div class="flex items-center gap-2 flex-1 min-w-0">
                <!-- Type indicator circle -->
                <LabelPillWidget
                    :label="`${object.type}: ${object.id}`"
                    :class="[objectColors.bgColor, objectColors.textColor]"
                    size="xs"
                />

                <!-- Main content -->
                <div class="flex-1 min-w-0">
                    <!-- Name and Type+ID pill -->
                    <div class="text-sm font-medium truncate flex-1" :title="object.name || 'Unnamed'">
                        {{ truncatedName }}
                    </div>
                </div>

                <!-- Confidence indicators -->
                <div class="flex items-center gap-1">
                    <LabelPillWidget
                        v-if="confidenceCounts.high > 0"
                        :label="confidenceCounts.high.toString()"
                        class="bg-green-500 text-green-100"
                        size="xs"
                        :title="`${confidenceCounts.high} high confidence`"
                    />
                    <LabelPillWidget
                        v-if="confidenceCounts.medium > 0"
                        :label="confidenceCounts.medium.toString()"
                        class="bg-amber-500 text-amber-100"
                        size="xs"
                        :title="`${confidenceCounts.medium} medium confidence`"
                    />
                    <LabelPillWidget
                        v-if="confidenceCounts.low > 0"
                        :label="confidenceCounts.low.toString()"
                        class="bg-red-500 text-red-100"
                        size="xs"
                        :title="`${confidenceCounts.low} low confidence`"
                    />
                </div>
            </div>
        </div>

        <!-- Children container with relationship grouping -->
        <div
            v-if="isExpanded && hasChildren"
            class="ml-4 border-l border-slate-600 pl-2 mt-2 space-y-3"
        >
            <div
                v-for="(relatedObjects, relationName) in object.relations"
                :key="relationName"
                v-show="relatedObjects.length > 0"
            >
                <!-- Relationship header -->
                <div class="flex items-center gap-2 px-2 py-1 text-xs font-medium italic text-slate-400 border-b border-slate-700/50 mb-2">
                    <component :is="getRelationshipIcon(relationName)" class="w-3 h-3 text-slate-400 flex-shrink-0" />
                    <span class="capitalize">{{ formatRelationName(relationName) }}</span>
                    <span class="text-slate-500">({{ relatedObjects.length }})</span>
                </div>

                <!-- Related objects under this relationship -->
                <div class="space-y-1">
                    <TreeNode
                        v-for="relatedObject in relatedObjects"
                        :key="relatedObject.id"
                        :object="relatedObject"
                        :selected-object="selectedObject"
                        :expanded-nodes="expandedNodes"
                        :level="level + 1"
                        @select="$emit('select', $event)"
                        @toggle="$emit('toggle', $event)"
                    />
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { getTypeColor } from "@/utils/typeColors";
import {
    FaSolidBuilding as BuildingIcon,
    FaSolidChevronRight as ChevronRightIcon,
    FaSolidFolder as FolderIcon,
    FaSolidGear as GearIcon,
    FaSolidLink as LinkIcon,
    FaSolidTag as TagIcon,
    FaSolidUsers as UsersIcon
} from "danx-icon";
import { LabelPillWidget } from "quasar-ui-danx";
import { computed } from "vue";
import type { TeamObject } from "./team-objects";

const props = defineProps<{
    object: TeamObject;
    selectedObject?: TeamObject | null;
    expandedNodes: Set<number>;
    level: number;
}>();

const emit = defineEmits<{
    select: [object: TeamObject];
    toggle: [objectId: number];
}>();

// Computed properties
const isExpanded = computed(() => props.expandedNodes.has(props.object.id));
const isSelected = computed(() => props.selectedObject?.id === props.object.id);
const hasChildren = computed(() =>
    Object.values(props.object.relations || {}).some(relations => relations.length > 0)
);
const childrenCount = computed(() =>
    Object.values(props.object.relations || {}).reduce((total, relations) => total + relations.length, 0)
);

// Color management
const objectColors = computed(() => getTypeColor(props.object.type));
const typeColor = computed(() => objectColors.value.bgColor);

// Truncated name for display
const truncatedName = computed(() => {
    const name = props.object.name || "Unnamed";
    return name.length > 25 ? name.substring(0, 25) + "..." : name;
});

// Confidence counts
const confidenceCounts = computed(() => {
    if (!props.object.attributes) return { high: 0, medium: 0, low: 0, none: 0 };

    return Object.values(props.object.attributes).reduce((acc: any, attr: any) => {
        const conf = attr.confidence?.toLowerCase() || "none";
        acc[conf] = (acc[conf] || 0) + 1;
        return acc;
    }, { high: 0, medium: 0, low: 0, none: 0 });
});

// Helper functions
const getRelationshipIcon = (relationName: string) => {
    const name = relationName.toLowerCase();

    if (name.includes("user") || name.includes("person") || name.includes("people") || name.includes("provider")) {
        return UsersIcon;
    } else if (name.includes("building") || name.includes("facility") || name.includes("location") || name.includes("place")) {
        return BuildingIcon;
    } else if (name.includes("config") || name.includes("setting") || name.includes("system")) {
        return GearIcon;
    } else if (name.includes("folder") || name.includes("directory") || name.includes("container")) {
        return FolderIcon;
    } else if (name.includes("tag") || name.includes("label") || name.includes("category")) {
        return TagIcon;
    } else {
        return LinkIcon;
    }
};

const formatRelationName = (name: string): string => {
    return name.replace(/_/g, " ").replace(/\b\w/g, l => l.toUpperCase());
};
</script>
