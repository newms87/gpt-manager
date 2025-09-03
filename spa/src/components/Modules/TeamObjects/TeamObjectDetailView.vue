<template>
    <div v-if="object" class="bg-slate-800 rounded-lg shadow-lg border border-slate-700 h-full flex flex-col">
        <div class="flex-1 overflow-y-auto">
            <div class="p-4 space-y-4">

                <!-- Compact Header Section -->
                <div class="flex items-center justify-between mb-2">
                    <div class="flex-x space-x-2">
                        <LabelPillWidget
                            :label="`${object.type}: ${object.id}`"
                            :class="[typeColors.bgColor, typeColors.textColor]"
                            size="md"
                        />
                        <h1 class="text-xl font-bold text-slate-100 leading-tight">
                            {{ object.name || "Unnamed Object" }}
                        </h1>
                        <div v-if="object.date" class="text-slate-400">
                            {{ fDate(object.date) }}
                        </div>

                        <a
                            v-if="object.url"
                            :href="object.url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="text-blue-400 hover:text-blue-300 transition-colors"
                        >
                            <component :is="ExternalLinkIcon" class="w-4 h-4" />
                        </a>
                    </div>

                    <!-- Parent Navigation Link -->
                    <a
                        v-if="parentObject"
                        @click="$emit('select-object', parentObject)"
                        class="flex-x gap-1 text-sm text-slate-400 hover:text-slate-200 transition-colors"
                    >
                        <LabelPillWidget
                            :class="[parentTypeColors.bgColor,parentTypeColors.textColor]"
                            class="flex-x space-x-2"
                            size="sm"
                        >
                            <ChevronUpIcon class="w-4 h-5" />
                            <div>{{ `${parentObject.type}: ${parentObject.id}` }}</div>
                        </LabelPillWidget>
                    </a>
                </div>

                <!-- Compact Description -->
                <div v-if="object.description" class="bg-slate-750 rounded-lg p-3 border border-slate-600">
                    <p class="text-slate-100 text-sm leading-relaxed text-center">
                        {{ object.description }}
                    </p>
                </div>

                <!-- Key Information Section -->
                <div v-if="attributeCount > 0" class="space-y-3">
                    <h2
                        class="text-lg font-bold border-b pb-1"
                        :class="[typeColors.textColor, typeColors.borderColor + '/30']"
                    >
                        🔑 Key Information
                    </h2>

                    <div class="grid grid-cols-12 gap-3">
                        <div
                            v-for="[name, attribute] in sortedAttributes"
                            :key="name"
                            :class="[getAttributeGridClass(attribute), typeColors.bgColorLight, typeColors.borderColorLight]"
                            class="border rounded-lg p-3 hover:border-opacity-70 transition-all duration-200 group relative"
                        >
                            <!-- Source indicators in top right -->
                            <div class="absolute top-2 right-2 flex gap-1">
                                <a
                                    v-for="source in attribute.sources?.slice(0, 3) || []"
                                    :key="source.id"
                                    :href="getSourceUrl(source)"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="text-slate-400 hover:text-emerald-300 transition-colors"
                                    :title="`Source: ${source.source_type}`"
                                >
                                    <component :is="getSourceIcon(source.source_type)" class="w-3 h-3" />
                                </a>
                            </div>

                            <!-- Attribute name as header -->
                            <h3
                                class="text-sm font-semibold mb-2 pr-8 leading-tight"
                                :class="typeColors.textColor"
                            >
                                {{ formatAttributeName(name) }}
                            </h3>

                            <!-- Main value -->
                            <div class="mb-2">
                                <div class="text-slate-100 text-base font-medium leading-snug whitespace-pre-wrap">
                                    {{ formatAttributeValue(attribute) }}
                                </div>
                            </div>

                            <!-- Confidence and meta info -->
                            <div class="flex items-center justify-between text-xs">
                                <ConfidenceIndicator :confidence="attribute.confidence" size="sm" />
                                <span class="text-slate-500">{{ fDate(attribute.updated_at) }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Relation Sections -->
                <RelatedObjectSection
                    v-for="(relatedObjects, relationName) in object.relations"
                    :key="relationName"
                    v-if="relationCount > 0"
                    :relation-name="relationName"
                    :related-objects="relatedObjects"
                    @select-object="$emit('select-object', $event)"
                />

            </div>
        </div>
    </div>

    <div v-else class="flex items-center justify-center h-64 text-slate-400">
        <div class="text-center space-y-2">
            <div class="text-xl">No object selected</div>
            <div class="text-sm">Choose an object from the list to view its details</div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { getTypeColor } from "@/utils/typeColors";
import {
    FaSolidChevronUp as ChevronUpIcon,
    FaSolidDatabase as DatabaseIcon,
    FaSolidFile as FileIcon,
    FaSolidGlobe as WebIcon,
    FaSolidLink as ExternalLinkIcon,
    FaSolidMessage as MessageIcon
} from "danx-icon";
import { fCurrency, fDate, fNumber, LabelPillWidget } from "quasar-ui-danx";
import { computed } from "vue";
import ConfidenceIndicator from "./ConfidenceIndicator.vue";
import RelatedObjectSection from "./RelatedObjectSection.vue";
import type { TeamObject, TeamObjectAttribute, TeamObjectAttributeSource } from "./team-objects";

const props = defineProps<{
    object?: TeamObject | null;
    parentObject?: TeamObject | null;
}>();

const emit = defineEmits<{
    "select-object": [object: TeamObject];
}>();

// Computed values for stats
const attributeCount = computed(() => {
    if (!props.object?.attributes) return 0;
    return Object.keys(props.object.attributes).length;
});

const relationCount = computed(() => {
    if (!props.object?.relations) return 0;
    return Object.values(props.object.relations).reduce((total, relations) => total + relations.length, 0);
});

const sourceCount = computed(() => {
    if (!props.object?.attributes) return 0;
    return Object.values(props.object.attributes).reduce((total, attr) => total + (attr.sources?.length || 0), 0);
});

// Color management

const typeColors = computed(() => {
    if (!props.object) return getTypeColor("default");
    return getTypeColor(props.object.type);
});

const parentTypeColors = computed(() => {
    if (!props.parentObject) return getTypeColor("default");
    return getTypeColor(props.parentObject.type);
});

// Sorted attributes by size (grid span) then by name
const sortedAttributes = computed(() => {
    if (!props.object?.attributes) return [];

    return Object.entries(props.object.attributes).sort(([nameA, attrA], [nameB, attrB]) => {
        // First sort by grid size (smaller first)
        const sizeA = getAttributeGridSize(attrA);
        const sizeB = getAttributeGridSize(attrB);

        if (sizeA !== sizeB) {
            return sizeA - sizeB;
        }

        // Then sort by name alphabetically
        return nameA.localeCompare(nameB);
    });
});

// Helper functions
const getSourceIcon = (sourceType: string) => {
    switch (sourceType.toLowerCase()) {
        case "file":
            return FileIcon;
        case "message":
            return MessageIcon;
        case "web":
            return WebIcon;
        case "database":
            return DatabaseIcon;
        default:
            return FileIcon;
    }
};

const formatAttributeName = (name: string): string => {
    // Convert snake_case and camelCase to human readable
    return name
        .replace(/[_-]/g, " ")
        .replace(/([a-z])([A-Z])/g, "$1 $2")
        .replace(/\b\w/g, l => l.toUpperCase());
};


const formatAttributeValue = (attribute: TeamObjectAttribute): string => {
    if (attribute.value === null || attribute.value === undefined) {
        return "No value";
    }

    const value = attribute.value;

    // Try to detect value type and format accordingly
    if (typeof value === "boolean") {
        return value ? "Yes" : "No";
    }

    if (typeof value === "number") {
        // Check if it looks like currency
        if (attribute.name.toLowerCase().includes("price") ||
            attribute.name.toLowerCase().includes("cost") ||
            attribute.name.toLowerCase().includes("amount")) {
            return fCurrency(value);
        }
        return fNumber(value);
    }

    if (typeof value === "string") {
        // Check if it looks like a date
        const dateRegex = /^\d{4}-\d{2}-\d{2}/;
        if (dateRegex.test(value)) {
            return fDate(value);
        }
    }

    if (Array.isArray(value)) {
        return value.join(", ");
    }

    if (typeof value === "object") {
        return JSON.stringify(value, null, 2);
    }

    return String(value);
};

const getSourceUrl = (source: TeamObjectAttributeSource): string => {
    if (source.sourceFile?.url) {
        return source.sourceFile.url;
    } else if (source.thread_url) {
        return source.thread_url;
    }
    return "#";
};

// Helper function to get grid size as number for sorting
const getAttributeGridSize = (attribute: TeamObjectAttribute): number => {
    const valueStr = String(attribute.value || "");
    const length = valueStr.length;

    if (length < 100) return 2;   // Very short: < 100 chars (2 cols)
    if (length < 200) return 3;   // Short: 100-299 chars (3 cols)
    if (length < 300) return 4;   // Medium: 300-799 chars (4 cols)
    if (length < 600) return 6;  // Long: 800-1999 chars (6 cols)
    return 12;                    // Very long: 2000+ chars (12 cols)
};

// Grid sizing function based on attribute value length
const getAttributeGridClass = (attribute: TeamObjectAttribute): string => {
    const size = getAttributeGridSize(attribute);
    return `col-span-${size}`;
};

</script>
