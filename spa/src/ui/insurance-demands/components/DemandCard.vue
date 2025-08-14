<template>
  <UiCard 
    clickable 
    class="demand-card hover:shadow-xl transition-all duration-300" 
    @click="$emit('view')"
  >
    <div class="space-y-4">
      <!-- Header -->
      <div class="flex items-start justify-between">
        <div class="flex-1 min-w-0">
          <h3 class="text-lg font-semibold text-slate-800 truncate">
            {{ demand.title }}
          </h3>
          <p v-if="demand.description" class="text-sm text-slate-600 mt-1 line-clamp-2">
            {{ demand.description }}
          </p>
        </div>
        
        <UiStatusBadge :status="demand.status" class="ml-4 flex-shrink-0" />
      </div>

      <!-- Progress Bar -->
      <UiProgressBar
        :value="progressPercentage"
        :color="progressColor"
        :label="`Progress: ${progressPercentage}%`"
        size="sm"
        :animated="hasActiveWorkflows"
      />

      <!-- File Count & Dates -->
      <div class="flex items-center justify-between text-sm text-slate-500">
        <div class="flex items-center space-x-4">
          <div class="flex items-center">
            <FaSolidPaperclip class="w-4 h-4 mr-1" />
            <span>{{ demand.files_count || 0 }} files</span>
          </div>
          
          <div class="flex items-center">
            <FaSolidClock class="w-4 h-4 mr-1" />
            <span>{{ formatDate(demand.created_at) }}</span>
          </div>
        </div>
        
        <div v-if="demand.completed_at" class="text-green-600 font-medium">
          Completed {{ formatDate(demand.completed_at) }}
        </div>
      </div>

      <!-- Workflow Progress Indicators -->
      <div v-if="hasActiveWorkflows" class="space-y-2">
        <!-- Extract Data Progress -->
        <div v-if="showExtractDataProgress" class="flex items-center justify-between text-xs">
          <span class="text-slate-600 font-medium">Extracting Data</span>
          <span class="text-blue-600 font-semibold">{{ demand.extract_data_workflow_run?.progress_percent || 0 }}%</span>
        </div>
        <div v-if="showExtractDataProgress" class="w-full bg-gray-200 rounded-full h-1.5">
          <div 
            class="bg-blue-500 h-1.5 rounded-full transition-all duration-300 ease-out"
            :style="{ width: `${demand.extract_data_workflow_run?.progress_percent || 0}%` }"
          />
        </div>
        
        <!-- Write Demand Progress -->
        <div v-if="showWriteDemandProgress" class="flex items-center justify-between text-xs">
          <span class="text-slate-600 font-medium">Writing Demand</span>
          <span class="text-green-600 font-semibold">{{ demand.write_demand_workflow_run?.progress_percent || 0 }}%</span>
        </div>
        <div v-if="showWriteDemandProgress" class="w-full bg-gray-200 rounded-full h-1.5">
          <div 
            class="bg-green-500 h-1.5 rounded-full transition-all duration-300 ease-out"
            :style="{ width: `${demand.write_demand_workflow_run?.progress_percent || 0}%` }"
          />
        </div>
      </div>

      <!-- Action Buttons -->
      <div class="flex items-center justify-between pt-2 border-t border-slate-100">
        <DemandActionButtons
          :demand="demand"
          size="sm"
          :loading-states="{
            extractData: extractingData,
            writeDemand: writingDemand
          }"
          @extract-data="handleExtractData"
          @write-demand="handleWriteDemand"
          @click.stop
        />
        
        <ActionButton
          v-if="[DEMAND_STATUS.DRAFT, DEMAND_STATUS.FAILED].includes(demand.status)"
          type="edit"
          size="sm"
          @click.stop="$emit('edit')"
        />
      </div>
    </div>
  </UiCard>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
import { 
  FaSolidPaperclip, 
  FaSolidClock 
} from 'danx-icon';
import { ActionButton } from 'quasar-ui-danx';
import { 
  UiCard, 
  UiStatusBadge, 
  UiProgressBar 
} from '../../shared/components';
import { useDemands } from '../composables';
import { DEMAND_STATUS, getDemandStatusColor, getDemandProgressPercentage } from '../config';
import type { UiDemand } from '../../shared/types';
import DemandActionButtons from './DemandActionButtons.vue';

const props = defineProps<{
  demand: UiDemand;
}>();

defineEmits<{
  edit: [];
  view: [];
}>();

const { extractData, writeDemand } = useDemands();

const extractingData = ref(false);
const writingDemand = ref(false);

const progressColor = computed(() => getDemandStatusColor(props.demand.status));
const progressPercentage = computed(() => getDemandProgressPercentage(props.demand.status));

// Workflow progress indicators
const showExtractDataProgress = computed(() => {
  const progress = props.demand.extract_data_workflow_run?.progress_percent;
  return progress != null && progress > 0 && progress < 100;
});

const showWriteDemandProgress = computed(() => {
  const progress = props.demand.write_demand_workflow_run?.progress_percent;
  return progress != null && progress > 0 && progress < 100;
});

const hasActiveWorkflows = computed(() => 
  showExtractDataProgress.value || showWriteDemandProgress.value
);

const formatDate = (dateString: string) => {
  return new Date(dateString).toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  });
};


const handleExtractData = async () => {
  try {
    extractingData.value = true;
    await extractData(props.demand.id);
  } catch (error) {
    console.error('Error extracting data:', error);
  } finally {
    extractingData.value = false;
  }
};

const handleWriteDemand = async () => {
  try {
    writingDemand.value = true;
    await writeDemand(props.demand.id);
  } catch (error) {
    console.error('Error writing demand:', error);
  } finally {
    writingDemand.value = false;
  }
};
</script>

<style scoped lang="scss">
.demand-card {
  &:hover {
    transform: translateY(-1px);
  }
}

.line-clamp-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
</style>