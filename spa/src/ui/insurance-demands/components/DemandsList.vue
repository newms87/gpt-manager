<template>
  <div class="demands-list">
    <!-- Loading State -->
    <div v-if="isLoading" class="flex items-center justify-center py-12">
      <UiLoadingSpinner size="lg" class="text-blue-500" />
      <span class="ml-3 text-slate-600">Loading demands...</span>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="bg-red-50 border border-red-200 rounded-lg p-4 text-red-700">
      <FaSolidExclamation class="w-5 h-5 inline mr-2" />
      {{ error }}
    </div>

    <!-- Empty State -->
    <UiEmptyState
      v-else-if="filteredDemands.length === 0 && !selectedStatus"
      title="No demands found"
      description="Get started by creating your first insurance demand."
      :icon="FaSolidFile"
    >
      <template #action>
        <ActionButton 
          type="create" 
          color="blue" 
          label="Create First Demand"
          @click="$emit('create')" 
        />
      </template>
    </UiEmptyState>

    <!-- No Results State -->
    <UiEmptyState
      v-else-if="filteredDemands.length === 0 && selectedStatus"
      title="No demands found"
      :description="`No demands with status '${selectedStatus}' found.`"
      :icon="FaSolidMagnifyingGlass"
    >
      <template #action>
        <ActionButton 
          type="cancel" 
          color="gray" 
          label="Clear Filter"
          @click="clearFilter" 
        />
      </template>
    </UiEmptyState>

    <!-- Demands Grid -->
    <div v-else class="space-y-6">
      <!-- Filter Info -->
      <div v-if="selectedStatus" class="flex items-center justify-between">
        <p class="text-sm text-slate-600">
          Showing {{ filteredDemands.length }} 
          {{ selectedStatus }} demand{{ filteredDemands.length !== 1 ? 's' : '' }}
        </p>
        <ActionButton type="clear" size="sm" label="Clear Filter" @click="clearFilter" />
      </div>

      <!-- Demands Grid -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <DemandCard
          v-for="demand in paginatedDemands"
          :key="demand.id"
          :demand="demand"
          @edit="$emit('edit', demand)"
          @view="$emit('view', demand)"
        />
      </div>

      <!-- Pagination -->
      <div v-if="totalPages > 1" class="flex justify-center mt-8">
        <div class="flex items-center space-x-2">
          <ActionButton
            type="prev"
            size="sm"
            :disabled="currentPage === 1"
            @click="currentPage = Math.max(1, currentPage - 1)"
          />

          <span class="px-4 py-2 text-sm text-slate-600">
            Page {{ currentPage }} of {{ totalPages }}
          </span>

          <ActionButton
            type="next"
            size="sm"
            :disabled="currentPage === totalPages"
            @click="currentPage = Math.min(totalPages, currentPage + 1)"
          />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { 
  FaSolidFile, 
  FaSolidMagnifyingGlass, 
  FaSolidXmark, 
  FaSolidExclamation,
  FaSolidChevronLeft,
  FaSolidChevronRight
} from 'danx-icon';
import { ActionButton } from 'quasar-ui-danx';
import { 
  UiEmptyState, 
  UiLoadingSpinner 
} from '../../shared/components';
import { useDemands } from '../composables';
import DemandCard from './DemandCard.vue';
import type { UiDemand } from '../../shared/types';

const props = withDefaults(defineProps<{
  statusFilter?: string;
  perPage?: number;
}>(), {
  perPage: 12,
});

defineEmits<{
  create: [];
  view: [demand: UiDemand];
  edit: [demand: UiDemand];
}>();

const { demands, isLoading, error } = useDemands();

const selectedStatus = ref(props.statusFilter);
const currentPage = ref(1);

const filteredDemands = computed(() => {
  if (!selectedStatus.value) return demands.value;
  return demands.value.filter(demand => demand.status === selectedStatus.value);
});

const totalPages = computed(() => {
  return Math.ceil(filteredDemands.value.length / props.perPage);
});

const paginatedDemands = computed(() => {
  const start = (currentPage.value - 1) * props.perPage;
  const end = start + props.perPage;
  return filteredDemands.value.slice(start, end);
});

const clearFilter = () => {
  selectedStatus.value = undefined;
  currentPage.value = 1;
};

// Reset page when filter changes
watch(selectedStatus, () => {
  currentPage.value = 1;
});

// Watch for external filter changes
watch(() => props.statusFilter, (newFilter) => {
  selectedStatus.value = newFilter;
});
</script>