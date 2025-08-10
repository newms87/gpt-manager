<template>
  <div class="plan-selection-card">
    <div
class="bg-white rounded-xl border border-slate-200 overflow-hidden hover:border-slate-300 transition-colors"
         :class="{
           'ring-2 ring-blue-500 border-blue-500': isCurrentPlan,
           'ring-2 ring-green-500 border-green-500': isSelected && !isCurrentPlan
         }">
      
      <!-- Plan Header -->
      <div
class="p-6 pb-4"
           :class="{
             'bg-gradient-to-r from-blue-50 to-purple-50': isCurrentPlan,
             'bg-gradient-to-r from-green-50 to-blue-50': isSelected && !isCurrentPlan
           }">
        <div class="flex items-start justify-between">
          <div>
            <h3 class="text-xl font-bold text-slate-800">{{ plan.name }}</h3>
            <p class="text-slate-600 mt-1">{{ plan.description }}</p>
          </div>
          
          <!-- Current Plan Badge -->
          <div
v-if="isCurrentPlan" 
               class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
            Current Plan
          </div>
          
          <!-- Selected Badge -->
          <div
v-else-if="isSelected" 
               class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
            <CheckIcon class="w-3 h-3 mr-1" />
            Selected
          </div>
        </div>

        <!-- Price -->
        <div class="mt-4">
          <div class="flex items-baseline">
            <span class="text-4xl font-bold text-slate-900">${{ plan.price }}</span>
            <span class="text-slate-600 ml-2">per {{ plan.interval }}</span>
          </div>
        </div>
      </div>

      <!-- Features -->
      <div class="px-6 py-4">
        <h4 class="text-sm font-semibold text-slate-800 mb-3">Features included:</h4>
        <ul class="space-y-2">
          <li
v-for="feature in plan.features" :key="feature" 
              class="flex items-start space-x-2">
            <CheckIcon class="w-4 h-4 text-green-500 flex-shrink-0 mt-0.5" />
            <span class="text-sm text-slate-600">{{ feature }}</span>
          </li>
        </ul>
      </div>

      <!-- Action Button -->
      <div class="px-6 pb-6">
        <ActionButton
          v-if="isCurrentPlan"
          variant="outline"
          color="blue"
          class="w-full"
          label="Current Plan"
          :disabled="true"
        />
        
        <ActionButton
          v-else-if="hasActiveSubscription && !isSelected"
          variant="primary"
          color="blue"
          class="w-full"
          :loading="isChangingPlan"
          :disabled="disabled"
          label="Change to This Plan"
          @click="handleChangePlan"
        />
        
        <ActionButton
          v-else-if="!hasActiveSubscription"
          variant="primary"
          color="green"
          class="w-full"
          :loading="isSubscribing"
          :disabled="disabled || !hasPaymentMethod"
          :label="hasPaymentMethod ? 'Subscribe Now' : 'Add Payment Method First'"
          @click="handleSubscribe"
        />
        
        <ActionButton
          v-else
          variant="primary"
          color="green"
          class="w-full"
          :loading="isSubscribing || isChangingPlan"
          :disabled="disabled"
          label="Confirm Selection"
          @click="handleConfirm"
        />
      </div>

      <!-- Payment Method Required Notice -->
      <div
v-if="!hasPaymentMethod && !hasActiveSubscription" 
           class="px-6 pb-4">
        <div class="flex items-start space-x-2 p-3 bg-amber-50 border border-amber-200 rounded-lg">
          <InfoIcon class="w-4 h-4 text-amber-600 flex-shrink-0 mt-0.5" />
          <p class="text-xs text-amber-700">
            A payment method is required to subscribe to this plan.
            <button class="underline hover:no-underline" @click="$emit('add-payment-method')">
              Add payment method
            </button>
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from "vue";
import { 
  FaSolidCheck as CheckIcon, 
  FaSolidInfo as InfoIcon 
} from "danx-icon";
import { ActionButton } from "quasar-ui-danx";
import { useBillingState } from "../Composables/useBillingState";
import type { SubscriptionPlan } from "../Types";

const props = defineProps<{
  plan: SubscriptionPlan;
  selected?: boolean;
  disabled?: boolean;
}>();

const emit = defineEmits<{
  'select': [plan: SubscriptionPlan];
  'subscribe': [plan: SubscriptionPlan];
  'change-plan': [plan: SubscriptionPlan];
  'add-payment-method': [];
}>();

// Composables
const {
  subscription,
  hasActiveSubscription,
  currentPlan,
  paymentMethods,
  isLoading,
  createSubscription,
  updateSubscription
} = useBillingState();

// Computed
const isSelected = computed(() => props.selected);

const isCurrentPlan = computed(() => 
  currentPlan.value?.id === props.plan.id
);

const hasPaymentMethod = computed(() => 
  paymentMethods.value.length > 0
);

const isSubscribing = computed(() => 
  isLoading.value && !hasActiveSubscription.value
);

const isChangingPlan = computed(() => 
  isLoading.value && hasActiveSubscription.value
);

// Methods
async function handleSubscribe() {
  if (!hasPaymentMethod.value) {
    emit('add-payment-method');
    return;
  }
  
  emit('subscribe', props.plan);
  
  try {
    const success = await createSubscription(props.plan.id);
    if (success) {
      console.log('Successfully subscribed to plan:', props.plan.name);
    }
  } catch (error) {
    console.error('Failed to subscribe:', error);
  }
}

async function handleChangePlan() {
  emit('change-plan', props.plan);
  
  try {
    const success = await updateSubscription(props.plan.id);
    if (success) {
      console.log('Successfully changed to plan:', props.plan.name);
    }
  } catch (error) {
    console.error('Failed to change plan:', error);
  }
}

function handleConfirm() {
  if (hasActiveSubscription.value) {
    handleChangePlan();
  } else {
    handleSubscribe();
  }
}

// Watch for selection changes
function handleCardClick() {
  if (!props.disabled && !isCurrentPlan.value) {
    emit('select', props.plan);
  }
}
</script>

<style scoped>
.plan-selection-card {
  cursor: pointer;
  transition: all 0.2s ease-in-out;
}

.plan-selection-card:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.plan-selection-card:active {
  transform: translateY(0);
}
</style>