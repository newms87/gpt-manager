<template>
    <div class="h-full bg-slate-900 p-6">
        <!-- Header -->
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-slate-100 mb-2">Team Objects Explorer</h1>
            <p class="text-slate-400">Explore and manage your team's object hierarchy</p>
        </div>

        <!-- Search and Filter Controls -->
        <div class="bg-slate-800 rounded-lg p-4 mb-6 border border-slate-700">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
                <!-- Search -->
                <div class="lg:col-span-2">
                    <TextField
                        v-model="searchQuery"
                        label="Search objects..."
                        placeholder="Search by name, type, or attributes"
                        class="w-full"
                    >
                        <template #prepend>
                            <SearchIcon class="w-4 h-4 text-slate-400" />
                        </template>
                    </TextField>
                </div>

                <!-- Object Type Filter -->
                <SelectField
                    v-model="selectedTypes"
                    label="Object Types"
                    :options="typeOptions"
                    multiple
                    clearable
                />

                <!-- Confidence Filter -->
                <SelectField
                    v-model="selectedConfidences"
                    label="Confidence Levels"
                    :options="confidenceOptions"
                    multiple
                    clearable
                />
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <!-- Date Range -->
                <div class="flex gap-2">
                    <DateField
                        v-model="dateRange.start"
                        label="From Date"
                        class="flex-1"
                    />
                    <DateField
                        v-model="dateRange.end"
                        label="To Date"
                        class="flex-1"
                    />
                </div>

                <!-- Sort Controls -->
                <SelectField
                    v-model="sortBy"
                    label="Sort By"
                    :options="sortOptions"
                />

                <!-- View Controls -->
                <div class="flex items-end gap-2">
                    <ActionButton
                        type="refresh"
                        color="slate"
                        tooltip="Refresh data"
                        @click="loadObjects"
                    />
                    <ShowHideButton
                        v-model="showFilters"
                        label="Advanced Filters"
                        size="sm"
                    />
                </div>
            </div>

            <!-- Advanced Filters (Collapsible) -->
            <div v-if="showFilters" class="mt-4 pt-4 border-t border-slate-700">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <NumberField
                        v-model="minAttributes"
                        label="Min Attributes"
                        :min="0"
                        placeholder="Minimum number of attributes"
                    />
                    <NumberField
                        v-model="minRelations"
                        label="Min Relations"
                        :min="0"
                        placeholder="Minimum number of relations"
                    />
                </div>
            </div>

            <!-- Filter Summary -->
            <div v-if="hasActiveFilters" class="mt-4 pt-4 border-t border-slate-700">
                <div class="flex items-center gap-2 text-sm text-slate-300">
                    <FilterIcon class="w-4 h-4" />
                    <span>{{ filteredObjects.length }} of {{ allObjects.length }} objects match filters</span>
                    <ActionButton
                        type="delete"
                        size="xs"
                        color="slate"
                        label="Clear all filters"
                        @click="clearFilters"
                    />
                </div>
            </div>
        </div>

        <!-- Main Content Layout -->
        <div class="flex gap-6 h-[calc(100vh-280px)]">
            <!-- Left Sidebar: Tree View -->
            <div class="w-1/3 min-w-0">
                <TeamObjectTreeView
                    :objects="filteredObjects"
                    :selected-object="selectedObject"
                    @select-object="onSelectObject"
                    @navigate="onNavigate"
                />
            </div>

            <!-- Right Content: Detail View -->
            <div class="flex-1 min-w-0">
                <TeamObjectDetailView
                    :object="selectedObject"
                    @select-object="onSelectObject"
                />
            </div>
        </div>

        <!-- Status Bar -->
        <div class="mt-6 text-center text-sm text-slate-400">
            Showing {{ filteredObjects.length }} of {{ allObjects.length }} objects
        </div>
    </div>
</template>

<script setup lang="ts">
import { FaSolidFilter as FilterIcon, FaSolidMagnifyingGlass as SearchIcon } from "danx-icon";
import { ActionButton, DateField, NumberField, SelectField, ShowHideButton, TextField } from "quasar-ui-danx";
import { computed, onMounted, ref } from "vue";
import { dxTeamObject } from "./config";
import type { TeamObject } from "./team-objects";
import TeamObjectDetailView from "./TeamObjectDetailView.vue";
import TeamObjectTreeView from "./TeamObjectTreeView.vue";

// State
const selectedObject = ref<TeamObject | null>(null);

// Filter state
const searchQuery = ref("");
const selectedTypes = ref<string[]>([]);
const selectedConfidences = ref<string[]>([]);
const dateRange = ref({
    start: "",
    end: ""
});
const sortBy = ref("name");
const showFilters = ref(false);
const minAttributes = ref<number | null>(null);
const minRelations = ref<number | null>(null);

// Controller state access
const allObjects = computed(() => {
    console.log("📊 TeamObjectExplorer: Computing allObjects from dxTeamObject.pagedItems...");
    const pagedData = dxTeamObject.pagedItems.value?.data;
    console.log("📦 TeamObjectExplorer: dxTeamObject.pagedItems.value:", dxTeamObject.pagedItems.value);
    console.log("📋 TeamObjectExplorer: pagedData:", pagedData);
    console.log("🔢 TeamObjectExplorer: pagedData length:", pagedData?.length || 0);
    return pagedData || [];
});

const isLoading = computed(() => dxTeamObject.isLoadingList.value);

// Watch for changes in pagedItems
const pagedItemsWatcher = computed(() => {
    console.log("👀 TeamObjectExplorer: pagedItems watcher triggered");
    console.log("📊 TeamObjectExplorer: dxTeamObject.pagedItems.value changed:", dxTeamObject.pagedItems.value);
    return dxTeamObject.pagedItems.value;
});

// Pagination
const currentPage = ref(1);
const itemsPerPage = ref(100);

// Filter options
const typeOptions = computed(() => {
    const types = new Set(allObjects.value.map(obj => obj.type));
    return Array.from(types).map(type => ({
        label: type,
        value: type
    }));
});

const confidenceOptions = [
    { label: "High", value: "high" },
    { label: "Medium", value: "medium" },
    { label: "Low", value: "low" },
    { label: "None", value: "none" }
];

const sortOptions = [
    { label: "Name", value: "name" },
    { label: "Type", value: "type" },
    { label: "Date", value: "date" },
    { label: "Attributes Count", value: "attributes_count" },
    { label: "Relations Count", value: "relations_count" }
];

// Computed values
const hasActiveFilters = computed(() => {
    return searchQuery.value ||
        selectedTypes.value.length > 0 ||
        selectedConfidences.value.length > 0 ||
        dateRange.value.start ||
        dateRange.value.end ||
        minAttributes.value !== null ||
        minRelations.value !== null;
});

const filteredObjects = computed(() => {
    console.log("🔍 TeamObjectExplorer: Computing filteredObjects...");
    console.log("📊 TeamObjectExplorer: allObjects.value in computed:", allObjects.value);
    console.log("🔢 TeamObjectExplorer: allObjects.value length in computed:", allObjects.value.length);

    // First trigger the watcher to log changes
    pagedItemsWatcher.value;

    let filtered = [...allObjects.value];
    console.log("📋 TeamObjectExplorer: Initial filtered array length:", filtered.length);

    // Search filter
    if (searchQuery.value.trim()) {
        console.log("🔍 TeamObjectExplorer: Applying search filter:", searchQuery.value);
        const query = searchQuery.value.toLowerCase().trim();
        const beforeSearchLength = filtered.length;
        filtered = filtered.filter(obj => {
            // Search in name, type, description
            if (obj.name?.toLowerCase().includes(query)) return true;
            if (obj.type?.toLowerCase().includes(query)) return true;
            if (obj.description?.toLowerCase().includes(query)) return true;

            // Search in attributes
            if (obj.attributes) {
                for (const [attrName, attr] of Object.entries(obj.attributes)) {
                    if (attrName.toLowerCase().includes(query)) return true;
                    if (typeof attr.value === "string" && attr.value.toLowerCase().includes(query)) return true;
                }
            }

            return false;
        });
        console.log(`🔍 TeamObjectExplorer: Search filter reduced from ${beforeSearchLength} to ${filtered.length} objects`);
    }

    // Type filter
    if (selectedTypes.value.length > 0) {
        console.log("🏷️ TeamObjectExplorer: Applying type filter:", selectedTypes.value);
        const beforeTypeLength = filtered.length;
        filtered = filtered.filter(obj => selectedTypes.value.includes(obj.type));
        console.log(`🏷️ TeamObjectExplorer: Type filter reduced from ${beforeTypeLength} to ${filtered.length} objects`);
    }

    // Confidence filter
    if (selectedConfidences.value.length > 0) {
        console.log("🎯 TeamObjectExplorer: Applying confidence filter:", selectedConfidences.value);
        const beforeConfidenceLength = filtered.length;
        filtered = filtered.filter(obj => {
            if (!obj.attributes) return false;
            return Object.values(obj.attributes).some(attr =>
                selectedConfidences.value.includes(attr.confidence?.toLowerCase() || "none")
            );
        });
        console.log(`🎯 TeamObjectExplorer: Confidence filter reduced from ${beforeConfidenceLength} to ${filtered.length} objects`);
    }

    // Date range filter
    if (dateRange.value.start) {
        console.log("📅 TeamObjectExplorer: Applying start date filter:", dateRange.value.start);
        const startDate = new Date(dateRange.value.start);
        const beforeStartDateLength = filtered.length;
        filtered = filtered.filter(obj => {
            const objDate = obj.date ? new Date(obj.date) : null;
            return objDate && objDate >= startDate;
        });
        console.log(`📅 TeamObjectExplorer: Start date filter reduced from ${beforeStartDateLength} to ${filtered.length} objects`);
    }

    if (dateRange.value.end) {
        console.log("📅 TeamObjectExplorer: Applying end date filter:", dateRange.value.end);
        const endDate = new Date(dateRange.value.end);
        const beforeEndDateLength = filtered.length;
        filtered = filtered.filter(obj => {
            const objDate = obj.date ? new Date(obj.date) : null;
            return objDate && objDate <= endDate;
        });
        console.log(`📅 TeamObjectExplorer: End date filter reduced from ${beforeEndDateLength} to ${filtered.length} objects`);
    }

    // Min attributes filter
    if (minAttributes.value !== null) {
        console.log("📊 TeamObjectExplorer: Applying min attributes filter:", minAttributes.value);
        const beforeMinAttrsLength = filtered.length;
        filtered = filtered.filter(obj => {
            const attrCount = obj.attributes ? Object.keys(obj.attributes).length : 0;
            return attrCount >= minAttributes.value!;
        });
        console.log(`📊 TeamObjectExplorer: Min attributes filter reduced from ${beforeMinAttrsLength} to ${filtered.length} objects`);
    }

    // Min relations filter
    if (minRelations.value !== null) {
        console.log("🔗 TeamObjectExplorer: Applying min relations filter:", minRelations.value);
        const beforeMinRelsLength = filtered.length;
        filtered = filtered.filter(obj => {
            const relCount = obj.relations
                ? Object.values(obj.relations).reduce((total, relations) => total + relations.length, 0)
                : 0;
            return relCount >= minRelations.value!;
        });
        console.log(`🔗 TeamObjectExplorer: Min relations filter reduced from ${beforeMinRelsLength} to ${filtered.length} objects`);
    }

    // Sort
    console.log(`🔀 TeamObjectExplorer: Sorting ${filtered.length} objects by ${sortBy.value}`);
    const sorted = sortObjects(filtered, sortBy.value);
    console.log("✅ TeamObjectExplorer: Final filtered and sorted objects:", sorted);
    console.log("🔢 TeamObjectExplorer: Final filtered objects length:", sorted.length);

    return sorted;
});

const totalFilteredObjects = computed(() => filteredObjects.value.length);

// Methods
const sortObjects = (objects: TeamObject[], sortField: string): TeamObject[] => {
    return objects.sort((a, b) => {
        switch (sortField) {
            case "name":
                return (a.name || "").localeCompare(b.name || "");
            case "type":
                return a.type.localeCompare(b.type);
            case "date":
                const dateA = a.date ? new Date(a.date).getTime() : 0;
                const dateB = b.date ? new Date(b.date).getTime() : 0;
                return dateB - dateA; // Most recent first
            case "attributes_count":
                const attrCountA = a.attributes ? Object.keys(a.attributes).length : 0;
                const attrCountB = b.attributes ? Object.keys(b.attributes).length : 0;
                return attrCountB - attrCountA; // Most attributes first
            case "relations_count":
                const relCountA = a.relations
                    ? Object.values(a.relations).reduce((total, relations) => total + relations.length, 0)
                    : 0;
                const relCountB = b.relations
                    ? Object.values(b.relations).reduce((total, relations) => total + relations.length, 0)
                    : 0;
                return relCountB - relCountA; // Most relations first
            default:
                return 0;
        }
    });
};

const initializeController = async () => {
    console.log("🔄 TeamObjectExplorer: Initializing dxTeamObject controller...");

    // Initialize the controller with required options
    dxTeamObject.initialize({
        isDetailsEnabled: false,
        isListEnabled: false, // We'll enable this after setup
        isSummaryEnabled: false,
        isFieldOptionsEnabled: false
    });

    console.log("✅ TeamObjectExplorer: dxTeamObject.initialize() completed");

    // Set pagination to get a large number of objects (or all of them)
    // Most explorer interfaces need to load all objects for filtering/searching
    console.log("📄 TeamObjectExplorer: Setting pagination to get all objects...");
    dxTeamObject.setPagination({ page: 1, rowsPerPage: 10000 }); // Large number to get all objects

    // Enable list loading to trigger data fetch - this will load ALL objects
    console.log("📡 TeamObjectExplorer: Setting isListEnabled: true to trigger data loading...");
    dxTeamObject.setOptions({ isListEnabled: true });
    dxTeamObject.setActiveFilter({
        type: "Demand",
        schema_definition_id: 71,
        root_object_id: { null: true }
    }); // Clear any active filters

    console.log("✅ TeamObjectExplorer: Controller initialization completed");
};

const loadObjects = () => {
    console.log("🔄 TeamObjectExplorer: loadObjects called - triggering controller refresh...");

    // Simply refresh the controller's data
    dxTeamObject.setOptions({ isListEnabled: true });

    console.log("✅ TeamObjectExplorer: Controller refresh triggered");
};

const onSelectObject = (object: TeamObject) => {
    selectedObject.value = object;
};

const onNavigate = (object: TeamObject) => {
    selectedObject.value = object;
};

const clearFilters = () => {
    searchQuery.value = "";
    selectedTypes.value = [];
    selectedConfidences.value = [];
    dateRange.value = { start: "", end: "" };
    minAttributes.value = null;
    minRelations.value = null;
    currentPage.value = 1;
};

// Initialize
onMounted(() => {
    initializeController();
});
</script>
