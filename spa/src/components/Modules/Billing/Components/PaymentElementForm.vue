<template>
  <div class="payment-element-form">
    <!-- Stripe Payment Element Container -->
    <div 
      id="payment-element" 
      ref="paymentElementRef" 
      class="min-h-[200px]"
    ></div>
    
    <!-- Loading State -->
    <div 
      v-if="isElementLoading" 
      class="absolute inset-0 flex items-center justify-center bg-white/80 rounded-lg"
    >
      <div class="flex items-center space-x-3">
        <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-600"></div>
        <span class="text-sm text-slate-600">Loading payment form...</span>
      </div>
    </div>

    <!-- Error Display -->
    <div v-if="elementError" class="mt-3 p-3 bg-red-50 border border-red-200 rounded-lg">
      <div class="flex items-start space-x-2">
        <ErrorIcon class="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" />
        <div>
          <h4 class="text-sm font-medium text-red-800">Payment Error</h4>
          <p class="text-sm text-red-700 mt-1">{{ elementError }}</p>
        </div>
      </div>
    </div>

    <!-- Success State -->
    <div v-if="isElementReady && !elementError" class="mt-3 flex items-center space-x-2">
      <CheckIcon class="w-4 h-4 text-green-500" />
      <span class="text-sm text-slate-600">Payment form ready</span>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted, nextTick, watch } from "vue";
import { FaSolidCheck as CheckIcon, FaSolidTriangleExclamation as ErrorIcon } from "danx-icon";
import { useStripe } from "../Composables/useStripe";
import type { StripeElementsOptions, PaymentElementChangeEvent } from "../Types";

const props = withDefaults(defineProps<{
  clientSecret: string;
  disabled?: boolean;
  appearance?: {
    theme?: 'stripe' | 'night' | 'flat';
    variables?: Record<string, string>;
  };
}>(), {
  disabled: false,
  appearance: () => ({ theme: 'stripe' })
});

const emit = defineEmits<{
  'ready': [];
  'change': [event: PaymentElementChangeEvent];
  'error': [error: string];
}>();

// Refs
const paymentElementRef = ref<HTMLElement>();
const isElementLoading = ref(true);
const isElementReady = ref(false);
const elementError = ref<string | null>(null);

// Composables
const {
  stripeInstance,
  isStripeLoaded,
  stripeError,
  loadStripe,
  createElements,
  createPaymentElement
} = useStripe();

// Setup Stripe and Payment Element
async function initializePaymentElement() {
  try {
    isElementLoading.value = true;
    elementError.value = null;

    // Load Stripe if not already loaded
    if (!isStripeLoaded.value) {
      const loaded = await loadStripe();
      if (!loaded) {
        throw new Error(stripeError.value || 'Failed to load Stripe');
      }
    }

    // Wait for DOM to be ready
    await nextTick();

    if (!paymentElementRef.value) {
      throw new Error('Payment element container not found');
    }

    // Create Stripe elements
    const elementsOptions: StripeElementsOptions = {
      clientSecret: props.clientSecret,
      appearance: props.appearance
    };

    const elements = createElements(elementsOptions);
    if (!elements) {
      throw new Error('Failed to create Stripe elements');
    }

    // Create payment element
    const paymentElement = createPaymentElement(elements, {
      layout: 'tabs'
    });

    if (!paymentElement) {
      throw new Error('Failed to create payment element');
    }

    // Mount the payment element
    paymentElement.mount(paymentElementRef.value);

    // Setup event listeners
    paymentElement.on('ready', () => {
      console.log('Payment element ready');
      isElementReady.value = true;
      isElementLoading.value = false;
      emit('ready');
    });

    paymentElement.on('change', (event: PaymentElementChangeEvent) => {
      console.log('Payment element changed:', event);
      
      if (event.error) {
        elementError.value = event.error.message;
        emit('error', event.error.message);
      } else {
        elementError.value = null;
      }
      
      emit('change', event);
    });

    paymentElement.on('focus', () => {
      console.log('Payment element focused');
    });

    paymentElement.on('blur', () => {
      console.log('Payment element blurred');
    });

  } catch (error: any) {
    console.error('Failed to initialize payment element:', error);
    elementError.value = error.message || 'Failed to initialize payment form';
    isElementLoading.value = false;
    emit('error', elementError.value);
  }
}

// Watch for clientSecret changes
watch(() => props.clientSecret, (newClientSecret) => {
  if (newClientSecret) {
    initializePaymentElement();
  }
}, { immediate: true });

// Lifecycle
onMounted(async () => {
  if (props.clientSecret) {
    await initializePaymentElement();
  }
});

onUnmounted(() => {
  // Cleanup is handled by useStripe composable
});

// Expose methods for parent component
defineExpose({
  isReady: isElementReady,
  hasError: elementError
});
</script>

<style scoped>
.payment-element-form {
  position: relative;
}

/* Customize Stripe element styles */
:deep(.StripeElement) {
  border-radius: 8px;
  border: 1px solid #d1d5db;
  padding: 12px;
  background-color: white;
}

:deep(.StripeElement--focus) {
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

:deep(.StripeElement--invalid) {
  border-color: #ef4444;
}
</style>