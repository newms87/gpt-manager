<template>
    <div class="h-full bg-slate-900 p-6">
        <!-- Header -->
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-100 mb-2">Team Objects Explorer</h1>
                <p class="text-slate-400">Explore and manage your team's object hierarchy</p>
            </div>
            
            <!-- Filter Controls -->
            <div class="flex items-center gap-2">
                <ActionButton
                    type="refresh"
                    color="slate"
                    tooltip="Refresh data"
                    @click="loadObjects"
                />
                <TeamObjectFilterPopover
                    v-model="filterValues"
                    :objects="allObjects"
                />
            </div>
        </div>

        <!-- Main Content Layout -->
        <div class="flex gap-6 h-[calc(100vh-280px)]">
            <!-- Left Sidebar: Tree View -->
            <div class="min-w-[25rem]">
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
                    :parent-object="parentObject"
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
import { ActionButton } from "quasar-ui-danx";
import { computed, onMounted, ref, watch } from "vue";
import { dxTeamObject } from "./config";
import type { TeamObject } from "./team-objects";
import TeamObjectDetailView from "./TeamObjectDetailView.vue";
import TeamObjectTreeView from "./TeamObjectTreeView.vue";
import TeamObjectFilterPopover from "./TeamObjectFilterPopover.vue";

// State
const selectedObject = ref<TeamObject | null>(null);
const parentObject = ref<TeamObject | null>(null);
const navigationStack = ref<TeamObject[]>([]);

// Filter state
const filterValues = ref({
    searchQuery: "",
    selectedTypes: [] as string[],
    selectedConfidences: [] as string[],
    dateRange: { start: "", end: "" },
    sortBy: "name",
    minAttributes: null as number | null,
    minRelations: null as number | null,
});

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

const filteredObjects = computed(() => {
    console.log("🔍 TeamObjectExplorer: Computing filteredObjects...");
    console.log("📊 TeamObjectExplorer: allObjects.value in computed:", allObjects.value);
    console.log("🔢 TeamObjectExplorer: allObjects.value length in computed:", allObjects.value.length);

    // First trigger the watcher to log changes
    pagedItemsWatcher.value;

    let filtered = [...allObjects.value];
    console.log("📋 TeamObjectExplorer: Initial filtered array length:", filtered.length);

    const filters = filterValues.value;

    // Search filter
    if (filters.searchQuery.trim()) {
        console.log("🔍 TeamObjectExplorer: Applying search filter:", filters.searchQuery);
        const query = filters.searchQuery.toLowerCase().trim();
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
    if (filters.selectedTypes.length > 0) {
        console.log("🏷️ TeamObjectExplorer: Applying type filter:", filters.selectedTypes);
        const beforeTypeLength = filtered.length;
        filtered = filtered.filter(obj => filters.selectedTypes.includes(obj.type));
        console.log(`🏷️ TeamObjectExplorer: Type filter reduced from ${beforeTypeLength} to ${filtered.length} objects`);
    }

    // Confidence filter
    if (filters.selectedConfidences.length > 0) {
        console.log("🎯 TeamObjectExplorer: Applying confidence filter:", filters.selectedConfidences);
        const beforeConfidenceLength = filtered.length;
        filtered = filtered.filter(obj => {
            if (!obj.attributes) return false;
            return Object.values(obj.attributes).some(attr =>
                filters.selectedConfidences.includes(attr.confidence?.toLowerCase() || "none")
            );
        });
        console.log(`🎯 TeamObjectExplorer: Confidence filter reduced from ${beforeConfidenceLength} to ${filtered.length} objects`);
    }

    // Date range filter
    if (filters.dateRange.start) {
        console.log("📅 TeamObjectExplorer: Applying start date filter:", filters.dateRange.start);
        const startDate = new Date(filters.dateRange.start);
        const beforeStartDateLength = filtered.length;
        filtered = filtered.filter(obj => {
            const objDate = obj.date ? new Date(obj.date) : null;
            return objDate && objDate >= startDate;
        });
        console.log(`📅 TeamObjectExplorer: Start date filter reduced from ${beforeStartDateLength} to ${filtered.length} objects`);
    }

    if (filters.dateRange.end) {
        console.log("📅 TeamObjectExplorer: Applying end date filter:", filters.dateRange.end);
        const endDate = new Date(filters.dateRange.end);
        const beforeEndDateLength = filtered.length;
        filtered = filtered.filter(obj => {
            const objDate = obj.date ? new Date(obj.date) : null;
            return objDate && objDate <= endDate;
        });
        console.log(`📅 TeamObjectExplorer: End date filter reduced from ${beforeEndDateLength} to ${filtered.length} objects`);
    }

    // Min attributes filter
    if (filters.minAttributes !== null) {
        console.log("📊 TeamObjectExplorer: Applying min attributes filter:", filters.minAttributes);
        const beforeMinAttrsLength = filtered.length;
        filtered = filtered.filter(obj => {
            const attrCount = obj.attributes ? Object.keys(obj.attributes).length : 0;
            return attrCount >= filters.minAttributes!;
        });
        console.log(`📊 TeamObjectExplorer: Min attributes filter reduced from ${beforeMinAttrsLength} to ${filtered.length} objects`);
    }

    // Min relations filter
    if (filters.minRelations !== null) {
        console.log("🔗 TeamObjectExplorer: Applying min relations filter:", filters.minRelations);
        const beforeMinRelsLength = filtered.length;
        filtered = filtered.filter(obj => {
            const relCount = obj.relations
                ? Object.values(obj.relations).reduce((total, relations) => total + relations.length, 0)
                : 0;
            return relCount >= filters.minRelations!;
        });
        console.log(`🔗 TeamObjectExplorer: Min relations filter reduced from ${beforeMinRelsLength} to ${filtered.length} objects`);
    }

    // Sort
    console.log(`🔀 TeamObjectExplorer: Sorting ${filtered.length} objects by ${filters.sortBy}`);
    const sorted = sortObjects(filtered, filters.sortBy);
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
    console.log("🔄 TeamObjectExplorer: Initializing controllers...");

    // Initialize the TeamObject controller
    dxTeamObject.initialize({
        isDetailsEnabled: false,
        isListEnabled: false, // We'll enable this after setup
        isSummaryEnabled: false,
        isFieldOptionsEnabled: false
    });

    console.log("✅ TeamObjectExplorer: Controller initialized");

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
    // Track parent navigation
    if (selectedObject.value && object.id !== selectedObject.value.id) {
        // Check if this is a child of current selection
        const isChild = Object.values(selectedObject.value.relations || {})
            .flat()
            .some(related => related.id === object.id);
        
        if (isChild) {
            // Going deeper, current selection becomes parent
            parentObject.value = selectedObject.value;
            navigationStack.value.push(selectedObject.value);
        } else if (parentObject.value && parentObject.value.id === object.id) {
            // Going back up to parent
            navigationStack.value.pop();
            parentObject.value = navigationStack.value[navigationStack.value.length - 1] || null;
        } else {
            // Lateral navigation or jumping to unrelated object
            parentObject.value = null;
            navigationStack.value = [];
        }
    }
    
    selectedObject.value = object;
};

const onNavigate = (object: TeamObject) => {
    selectedObject.value = object;
};


// Initialize
onMounted(() => {
    initializeController();
});
</script>
