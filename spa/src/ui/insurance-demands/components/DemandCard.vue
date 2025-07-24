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
        :animated="demand.status === DEMAND_STATUS.PROCESSING"
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

      <!-- Action Buttons -->
      <div class="flex items-center justify-between pt-2 border-t border-slate-100">
        <div class="flex space-x-2">
          <ActionButton
            v-if="demand.status === DEMAND_STATUS.DRAFT && demand.can_be_submitted"
            type="save"
            size="sm"
            :loading="submitting"
            label="Submit"
            @click.stop="handleSubmit"
          />
          
          <ActionButton
            v-if="demand.status === DEMAND_STATUS.READY"
            type="play"
            size="sm"
            :loading="processing"
            label="Write Demand"
            @click.stop="handleRunWorkflow"
          />
        </div>
        
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

const props = defineProps<{
  demand: UiDemand;
}>();

defineEmits<{
  edit: [];
  view: [];
}>();

const { submitDemand, runWorkflow } = useDemands();

const submitting = ref(false);
const processing = ref(false);

const progressColor = computed(() => getDemandStatusColor(props.demand.status));
const progressPercentage = computed(() => getDemandProgressPercentage(props.demand.status));

const formatDate = (dateString: string) => {
  return new Date(dateString).toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  });
};

const handleSubmit = async () => {
  try {
    submitting.value = true;
    await submitDemand(props.demand.id);
  } catch (error) {
    console.error('Error submitting demand:', error);
  } finally {
    submitting.value = false;
  }
};

const handleRunWorkflow = async () => {
  try {
    processing.value = true;
    await runWorkflow(props.demand.id);
  } catch (error) {
    console.error('Error running workflow:', error);
  } finally {
    processing.value = false;
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