<template>
  <div class="relative">
    <!-- Filter toggle button with counter badge -->
    <ShowHideButton
      v-model="isShowing"
      class="flex-shrink-0"
      :show-icon="FilterIcon"
      size="sm"
      tooltip="Toggle Filters"
      :label="activeFilterCount > 0 ? activeFilterCount.toString() : ''"
      :class="{'bg-slate-700': activeFilterCount === 0, 'bg-sky-600': activeFilterCount > 0}"
    />

    <!-- Filter popup menu -->
    <PopoverMenu v-model="isShowing" placement="bottom" close-on-click-outside>
      <div class="bg-slate-700 p-4 rounded-lg shadow-xl w-80">
        <h3 class="text-sm font-semibold mb-4 text-slate-200">Filter Team Objects</h3>

        <div class="space-y-4">
          <!-- Search -->
          <div>
            <label class="block text-xs font-medium text-slate-300 mb-1">Search</label>
            <TextField
              v-model="searchQuery"
              placeholder="Search by name, type, or attributes"
              size="sm"
            >
              <template #prepend>
                <SearchIcon class="w-4 h-4 text-slate-400" />
              </template>
            </TextField>
          </div>

          <!-- Object Type Filter -->
          <div>
            <label class="block text-xs font-medium text-slate-300 mb-1">Object Types</label>
            <SelectField
              v-model="selectedTypes"
              :options="typeOptions"
              multiple
              clearable
              size="sm"
              placeholder="All types"
            />
          </div>

          <!-- Confidence Filter -->
          <div>
            <label class="block text-xs font-medium text-slate-300 mb-1">Confidence Levels</label>
            <SelectField
              v-model="selectedConfidences"
              :options="confidenceOptions"
              multiple
              clearable
              size="sm"
              placeholder="All confidence levels"
            />
          </div>

          <!-- Date Range -->
          <div class="grid grid-cols-2 gap-2">
            <div>
              <label class="block text-xs font-medium text-slate-300 mb-1">From Date</label>
              <DateField
                v-model="dateRange.start"
                size="sm"
              />
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-300 mb-1">To Date</label>
              <DateField
                v-model="dateRange.end"
                size="sm"
              />
            </div>
          </div>

          <!-- Sort Controls -->
          <div>
            <label class="block text-xs font-medium text-slate-300 mb-1">Sort By</label>
            <SelectField
              v-model="sortBy"
              :options="sortOptions"
              size="sm"
            />
          </div>

          <!-- Advanced Filters Toggle -->
          <div>
            <ShowHideButton
              v-model="showAdvancedFilters"
              label="Advanced Filters"
              size="sm"
              class="text-slate-300"
            />
          </div>

          <!-- Advanced Filters (Collapsible) -->
          <div v-if="showAdvancedFilters" class="space-y-3 pt-3 border-t border-slate-600">
            <div class="grid grid-cols-2 gap-2">
              <div>
                <label class="block text-xs font-medium text-slate-300 mb-1">Min Attributes</label>
                <NumberField
                  v-model="minAttributes"
                  :min="0"
                  placeholder="Any"
                  size="sm"
                />
              </div>
              <div>
                <label class="block text-xs font-medium text-slate-300 mb-1">Min Relations</label>
                <NumberField
                  v-model="minRelations"
                  :min="0"
                  placeholder="Any"
                  size="sm"
                />
              </div>
            </div>
          </div>
        </div>

        <!-- Reset filters button -->
        <div class="mt-4 pt-4 border-t border-slate-600">
          <ActionButton
            type="reset"
            label="Clear All Filters"
            size="sm"
            class="w-full"
            color="slate"
            @click="clearAllFilters"
          />
        </div>
      </div>
    </PopoverMenu>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';
import { 
  ActionButton, 
  DateField, 
  NumberField, 
  SelectField, 
  ShowHideButton, 
  TextField 
} from 'quasar-ui-danx';
import { 
  FaSolidFilter as FilterIcon, 
  FaSolidMagnifyingGlass as SearchIcon 
} from 'danx-icon';
import PopoverMenu from '@/components/Shared/Utilities/PopoverMenu.vue';
import type { TeamObject } from './team-objects';

// Props
const props = defineProps<{
  objects: TeamObject[];
}>();

// Model for all filter values
const filters = defineModel<{
  searchQuery: string;
  selectedTypes: string[];
  selectedConfidences: string[];
  dateRange: { start: string; end: string };
  sortBy: string;
  minAttributes: number | null;
  minRelations: number | null;
}>();

// Popover state
const isShowing = ref(false);
const showAdvancedFilters = ref(false);

// Individual filter reactive refs
const searchQuery = computed({
  get: () => filters.value?.searchQuery || '',
  set: (value) => {
    if (filters.value) {
      filters.value.searchQuery = value;
    }
  }
});

const selectedTypes = computed({
  get: () => filters.value?.selectedTypes || [],
  set: (value) => {
    if (filters.value) {
      filters.value.selectedTypes = value;
    }
  }
});

const selectedConfidences = computed({
  get: () => filters.value?.selectedConfidences || [],
  set: (value) => {
    if (filters.value) {
      filters.value.selectedConfidences = value;
    }
  }
});

const dateRange = computed({
  get: () => filters.value?.dateRange || { start: '', end: '' },
  set: (value) => {
    if (filters.value) {
      filters.value.dateRange = value;
    }
  }
});

const sortBy = computed({
  get: () => filters.value?.sortBy || 'name',
  set: (value) => {
    if (filters.value) {
      filters.value.sortBy = value;
    }
  }
});

const minAttributes = computed({
  get: () => filters.value?.minAttributes || null,
  set: (value) => {
    if (filters.value) {
      filters.value.minAttributes = value;
    }
  }
});

const minRelations = computed({
  get: () => filters.value?.minRelations || null,
  set: (value) => {
    if (filters.value) {
      filters.value.minRelations = value;
    }
  }
});

// Filter options
const typeOptions = computed(() => {
  const types = new Set(props.objects.map(obj => obj.type));
  return Array.from(types).map(type => ({
    label: type,
    value: type
  }));
});

const confidenceOptions = [
  { label: 'High', value: 'high' },
  { label: 'Medium', value: 'medium' },
  { label: 'Low', value: 'low' },
  { label: 'None', value: 'none' }
];

const sortOptions = [
  { label: 'Name', value: 'name' },
  { label: 'Type', value: 'type' },
  { label: 'Date', value: 'date' },
  { label: 'Attributes Count', value: 'attributes_count' },
  { label: 'Relations Count', value: 'relations_count' }
];

// Count active filters
const activeFilterCount = computed(() => {
  if (!filters.value) return 0;
  
  let count = 0;
  
  if (filters.value.searchQuery?.trim()) count++;
  if (filters.value.selectedTypes?.length > 0) count++;
  if (filters.value.selectedConfidences?.length > 0) count++;
  if (filters.value.dateRange?.start) count++;
  if (filters.value.dateRange?.end) count++;
  if (filters.value.minAttributes !== null) count++;
  if (filters.value.minRelations !== null) count++;
  
  return count;
});

// Methods
const clearAllFilters = () => {
  if (filters.value) {
    filters.value.searchQuery = '';
    filters.value.selectedTypes = [];
    filters.value.selectedConfidences = [];
    filters.value.dateRange = { start: '', end: '' };
    filters.value.sortBy = 'name';
    filters.value.minAttributes = null;
    filters.value.minRelations = null;
  }
  isShowing.value = false;
};
</script>