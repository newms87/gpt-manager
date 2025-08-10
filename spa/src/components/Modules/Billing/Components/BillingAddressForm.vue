<template>
  <div class="billing-address-form">
    <div class="space-y-4">
      <!-- Address Line 1 -->
      <TextField
        v-model="address.line1"
        label="Address Line 1"
        required
        placeholder="123 Main Street"
        :error="errors.line1"
        @update:model-value="clearError('line1')"
      />

      <!-- Address Line 2 -->
      <TextField
        v-model="address.line2"
        label="Address Line 2"
        placeholder="Apartment, suite, etc. (optional)"
        :error="errors.line2"
        @update:model-value="clearError('line2')"
      />

      <!-- City, State, Postal Code -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <TextField
          v-model="address.city"
          label="City"
          required
          placeholder="New York"
          :error="errors.city"
          @update:model-value="clearError('city')"
        />

        <SelectField
          v-model="address.state"
          label="State"
          required
          :options="stateOptions"
          placeholder="Select state"
          :error="errors.state"
          @update:model-value="clearError('state')"
        />

        <TextField
          v-model="address.postal_code"
          label="Postal Code"
          required
          placeholder="10001"
          :error="errors.postal_code"
          @update:model-value="clearError('postal_code')"
        />
      </div>

      <!-- Country -->
      <SelectField
        v-model="address.country"
        label="Country"
        required
        :options="countryOptions"
        placeholder="Select country"
        :error="errors.country"
        @update:model-value="clearError('country')"
      />
    </div>

    <!-- Validation Summary -->
    <div v-if="hasErrors" class="mt-4 p-3 bg-red-50 border border-red-200 rounded-lg">
      <div class="flex items-start space-x-2">
        <ErrorIcon class="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" />
        <div>
          <h4 class="text-sm font-medium text-red-800">Please correct the following errors:</h4>
          <ul class="text-sm text-red-700 mt-1 list-disc list-inside">
            <li v-for="error in errorList" :key="error">{{ error }}</li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { reactive, computed, watch } from "vue";
import { TextField, SelectField } from "quasar-ui-danx";
import { FaSolidTriangleExclamation as ErrorIcon } from "danx-icon";
import type { BillingAddress } from "../Types";

const props = withDefaults(defineProps<{
  modelValue?: BillingAddress;
  disabled?: boolean;
}>(), {
  disabled: false
});

const emit = defineEmits<{
  'update:modelValue': [value: BillingAddress];
  'validate': [isValid: boolean];
}>();

// State
const address = reactive<BillingAddress>({
  line1: props.modelValue?.line1 || '',
  line2: props.modelValue?.line2 || '',
  city: props.modelValue?.city || '',
  state: props.modelValue?.state || '',
  postal_code: props.modelValue?.postal_code || '',
  country: props.modelValue?.country || 'US'
});

const errors = reactive({
  line1: '',
  line2: '',
  city: '',
  state: '',
  postal_code: '',
  country: ''
});

// Options
const stateOptions = [
  { label: 'Alabama', value: 'AL' },
  { label: 'Alaska', value: 'AK' },
  { label: 'Arizona', value: 'AZ' },
  { label: 'Arkansas', value: 'AR' },
  { label: 'California', value: 'CA' },
  { label: 'Colorado', value: 'CO' },
  { label: 'Connecticut', value: 'CT' },
  { label: 'Delaware', value: 'DE' },
  { label: 'Florida', value: 'FL' },
  { label: 'Georgia', value: 'GA' },
  { label: 'Hawaii', value: 'HI' },
  { label: 'Idaho', value: 'ID' },
  { label: 'Illinois', value: 'IL' },
  { label: 'Indiana', value: 'IN' },
  { label: 'Iowa', value: 'IA' },
  { label: 'Kansas', value: 'KS' },
  { label: 'Kentucky', value: 'KY' },
  { label: 'Louisiana', value: 'LA' },
  { label: 'Maine', value: 'ME' },
  { label: 'Maryland', value: 'MD' },
  { label: 'Massachusetts', value: 'MA' },
  { label: 'Michigan', value: 'MI' },
  { label: 'Minnesota', value: 'MN' },
  { label: 'Mississippi', value: 'MS' },
  { label: 'Missouri', value: 'MO' },
  { label: 'Montana', value: 'MT' },
  { label: 'Nebraska', value: 'NE' },
  { label: 'Nevada', value: 'NV' },
  { label: 'New Hampshire', value: 'NH' },
  { label: 'New Jersey', value: 'NJ' },
  { label: 'New Mexico', value: 'NM' },
  { label: 'New York', value: 'NY' },
  { label: 'North Carolina', value: 'NC' },
  { label: 'North Dakota', value: 'ND' },
  { label: 'Ohio', value: 'OH' },
  { label: 'Oklahoma', value: 'OK' },
  { label: 'Oregon', value: 'OR' },
  { label: 'Pennsylvania', value: 'PA' },
  { label: 'Rhode Island', value: 'RI' },
  { label: 'South Carolina', value: 'SC' },
  { label: 'South Dakota', value: 'SD' },
  { label: 'Tennessee', value: 'TN' },
  { label: 'Texas', value: 'TX' },
  { label: 'Utah', value: 'UT' },
  { label: 'Vermont', value: 'VT' },
  { label: 'Virginia', value: 'VA' },
  { label: 'Washington', value: 'WA' },
  { label: 'West Virginia', value: 'WV' },
  { label: 'Wisconsin', value: 'WI' },
  { label: 'Wyoming', value: 'WY' }
];

const countryOptions = [
  { label: 'United States', value: 'US' },
  { label: 'Canada', value: 'CA' },
  { label: 'United Kingdom', value: 'GB' },
  { label: 'Australia', value: 'AU' },
  { label: 'Germany', value: 'DE' },
  { label: 'France', value: 'FR' },
  { label: 'Italy', value: 'IT' },
  { label: 'Spain', value: 'ES' },
  { label: 'Netherlands', value: 'NL' },
  { label: 'Belgium', value: 'BE' },
  { label: 'Switzerland', value: 'CH' },
  { label: 'Austria', value: 'AT' },
  { label: 'Sweden', value: 'SE' },
  { label: 'Norway', value: 'NO' },
  { label: 'Denmark', value: 'DK' },
  { label: 'Finland', value: 'FI' },
  { label: 'Japan', value: 'JP' },
  { label: 'South Korea', value: 'KR' },
  { label: 'Singapore', value: 'SG' },
  { label: 'New Zealand', value: 'NZ' }
];

// Computed
const hasErrors = computed(() => 
  Object.values(errors).some(error => error !== '')
);

const errorList = computed(() => 
  Object.values(errors).filter(error => error !== '')
);

const isValid = computed(() => {
  return !hasErrors.value &&
    address.line1.trim() !== '' &&
    address.city.trim() !== '' &&
    address.state.trim() !== '' &&
    address.postal_code.trim() !== '' &&
    address.country.trim() !== '';
});

// Methods
function clearError(field: keyof typeof errors) {
  errors[field] = '';
}

function validateField(field: keyof BillingAddress, value: string): string {
  if (!value.trim()) {
    const labels: Record<keyof BillingAddress, string> = {
      line1: 'Address Line 1',
      line2: 'Address Line 2',
      city: 'City',
      state: 'State',
      postal_code: 'Postal Code',
      country: 'Country'
    };
    return `${labels[field]} is required`;
  }
  
  // Specific validation rules
  if (field === 'postal_code') {
    const postalRegex = address.country === 'US' 
      ? /^\d{5}(-\d{4})?$/ 
      : /^[A-Za-z0-9\s-]{3,10}$/;
    if (!postalRegex.test(value)) {
      return address.country === 'US' 
        ? 'Please enter a valid US postal code (12345 or 12345-6789)'
        : 'Please enter a valid postal code';
    }
  }
  
  return '';
}

function validate(): boolean {
  const requiredFields: (keyof BillingAddress)[] = ['line1', 'city', 'state', 'postal_code', 'country'];
  
  for (const field of requiredFields) {
    const value = address[field] || '';
    const error = validateField(field, value);
    errors[field] = error;
  }
  
  return isValid.value;
}

// Watchers
watch(address, (newAddress) => {
  emit('update:modelValue', { ...newAddress });
}, { deep: true });

watch(isValid, (valid) => {
  emit('validate', valid);
});

// Initialize from props
watch(() => props.modelValue, (newValue) => {
  if (newValue) {
    Object.assign(address, newValue);
  }
}, { immediate: true, deep: true });

// Expose validation method
defineExpose({
  validate,
  isValid
});
</script>