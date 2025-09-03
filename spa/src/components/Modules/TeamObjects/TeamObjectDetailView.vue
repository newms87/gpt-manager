<template>
    <div v-if="object" class="bg-slate-800 rounded-lg shadow-lg border border-slate-700 h-full flex flex-col">
        <div class="flex-1 overflow-y-auto">
            <div class="p-4 space-y-4">

                <!-- Compact Header Section -->
                <div class="flex-x space-x-2">
                    <LabelPillWidget
                        :label="`${object.type}: ${object.id}`"
                        :class="[typeColors.bgColor, typeColors.textColor]"
                        size="md"
                    />
                    <h1 class="text-xl font-bold text-slate-100 leading-tight">
                        {{ object.name || "Unnamed Object" }}
                    </h1>

                    <div class="flex items-center justify-center gap-2 flex-wrap text-sm">
            <span v-if="object.date" class="text-slate-400">
              {{ fDate(object.date) }}
            </span>

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
                            v-for="(attribute, name) in object.attributes"
                            :key="name"
                            :class="getAttributeGridClass(attribute)"
                            class="bg-gradient-to-br border rounded-lg p-3 hover:border-opacity-70 transition-all duration-200 group relative"
                            :style="getAttributeStyles(typeColors)"
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
                                <div class="text-slate-100 text-base font-medium leading-snug">
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
                <div
                    v-for="(relatedObjects, relationName) in object.relations"
                    :key="relationName"
                    v-if="relationCount > 0"
                    class="space-y-3"
                >
                    <!-- Relation Header -->
                    <h2
                        class="text-lg font-bold border-b pb-1"
                        :class="[getRelationTypeColors(relatedObjects).textColor, getRelationTypeColors(relatedObjects).borderColor + '/30']"
                    >
                        🔗 {{ formatRelationName(relationName) }}
                        <span class="text-xs font-normal text-slate-400 ml-1">
                            ({{ relatedObjects.length }})
                        </span>
                    </h2>

                    <!-- Related Objects - Full Width Rows -->
                    <div class="space-y-2">
                        <div
                            v-for="relatedObject in relatedObjects"
                            :key="relatedObject.id"
                            class="w-full rounded-lg p-3 border cursor-pointer transition-all duration-200 group"
                            :style="getRelatedObjectStyles(relatedObject)"
                            @click="$emit('select-object', relatedObject)"
                        >
                            <div class="flex items-center justify-between">
                                <!-- Left: Name and Type -->
                                <div class="flex items-center gap-3 flex-1">
                                    <LabelPillWidget
                                        :label="relatedObject.type + ': ' + relatedObject.id"
                                        :class="[getTypeColor(relatedObject.type, schemaDefinition).bgColor, getTypeColor(relatedObject.type, schemaDefinition).textColor]"
                                        size="xs"
                                    />
                                    <div class="text-sm font-medium text-slate-100 group-hover:text-white transition-colors">
                                        {{ relatedObject.name || "Unnamed" }}
                                    </div>
                                </div>

                                <!-- Right: ID and Date -->
                                <div class="flex items-center gap-3 text-xs text-slate-400">
                                    <span v-if="relatedObject.date">{{ fDate(relatedObject.date) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

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
import { dxSchemaDefinition } from "@/components/Modules/Schemas/SchemaDefinitions/config";
import { getAttributeStyles, getTypeColor } from "@/utils/typeColors";
import {
    FaSolidDatabase as DatabaseIcon,
    FaSolidFile as FileIcon,
    FaSolidGlobe as WebIcon,
    FaSolidLink as ExternalLinkIcon,
    FaSolidMessage as MessageIcon
} from "danx-icon";
import { fCurrency, fDate, fNumber, LabelPillWidget } from "quasar-ui-danx";
import { computed } from "vue";
import ConfidenceIndicator from "./ConfidenceIndicator.vue";
import type { TeamObject, TeamObjectAttribute, TeamObjectAttributeSource } from "./team-objects";

const props = defineProps<{
    object?: TeamObject | null;
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
const schemaDefinition = computed(() => dxSchemaDefinition.pagedItems.value?.data?.[0]);

const typeColors = computed(() => {
    if (!props.object) return getTypeColor("default");
    return getTypeColor(props.object.type, schemaDefinition.value);
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

const formatRelationName = (name: string): string => {
    // Convert snake_case to human readable and make it more natural
    return name
        .replace(/_/g, " ")
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

        // Limit very long strings
        if (value.length > 200) {
            return value.substring(0, 200) + "...";
        }
    }

    if (Array.isArray(value)) {
        return value.slice(0, 5).join(", ") + (value.length > 5 ? "..." : "");
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

// Grid sizing function based on attribute value length
const getAttributeGridClass = (attribute: TeamObjectAttribute): string => {
    const valueStr = String(attribute.value || "");
    const length = valueStr.length;

    if (length < 20) return "col-span-1";
    if (length < 40) return "col-span-2";
    if (length < 80) return "col-span-3";
    if (length < 150) return "col-span-4";
    if (length < 300) return "col-span-6";
    return "col-span-12";
};

// Get relation type colors based on most common type in the relationship
const getRelationTypeColors = (relatedObjects: TeamObject[]) => {
    if (relatedObjects.length === 0) return getTypeColor("default");

    // Use the most common type in the relationship
    const typeCounts = relatedObjects.reduce((acc, obj) => {
        acc[obj.type] = (acc[obj.type] || 0) + 1;
        return acc;
    }, {} as Record<string, number>);

    const mostCommonType = Object.keys(typeCounts).reduce((a, b) =>
        typeCounts[a] > typeCounts[b] ? a : b
    );

    return getTypeColor(mostCommonType, schemaDefinition.value);
};

// Get styles for individual related objects
const getRelatedObjectStyles = (relatedObject: TeamObject) => {
    const objectColors = getTypeColor(relatedObject.type, schemaDefinition.value);
    return getAttributeStyles(objectColors);
};
</script>
