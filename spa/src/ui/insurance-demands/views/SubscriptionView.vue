<template>
  <UiMainLayout>
    <template #header>
      <div class="px-6 py-4">
        <h1 class="text-2xl font-bold text-slate-800">
          Subscription & Billing
        </h1>
        <p class="text-slate-600 mt-1">
          Manage your subscription plan and billing information
        </p>
      </div>
    </template>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Main Content -->
      <div class="lg:col-span-2 space-y-6">
        <!-- Current Plan -->
        <UiCard>
          <template #header>
            <div class="flex items-center justify-between">
              <h3 class="text-lg font-semibold text-slate-800">
                Current Plan
              </h3>
              <UiStatusBadge status="completed" size="sm">
                Active
              </UiStatusBadge>
            </div>
          </template>

          <div class="space-y-6">
            <div class="flex items-center justify-between p-4 bg-gradient-to-r from-blue-50 to-purple-50 rounded-lg border border-blue-200">
              <div>
                <h4 class="text-xl font-bold text-slate-800">Professional Plan</h4>
                <p class="text-slate-600">Unlimited demands with priority processing</p>
              </div>
              <div class="text-right">
                <p class="text-3xl font-bold text-blue-600">$49</p>
                <p class="text-sm text-slate-600">per month</p>
              </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <h5 class="font-semibold text-slate-800 mb-3">Plan Features</h5>
                <ul class="space-y-2">
                  <li class="flex items-center text-sm text-slate-600">
                    <FaSolidCheck class="w-4 h-4 text-green-500 mr-2" />
                    Unlimited demand submissions
                  </li>
                  <li class="flex items-center text-sm text-slate-600">
                    <FaSolidCheck class="w-4 h-4 text-green-500 mr-2" />
                    Priority processing queue
                  </li>
                  <li class="flex items-center text-sm text-slate-600">
                    <FaSolidCheck class="w-4 h-4 text-green-500 mr-2" />
                    24/7 customer support
                  </li>
                  <li class="flex items-center text-sm text-slate-600">
                    <FaSolidCheck class="w-4 h-4 text-green-500 mr-2" />
                    Advanced reporting
                  </li>
                </ul>
              </div>

              <div>
                <h5 class="font-semibold text-slate-800 mb-3">Usage This Month</h5>
                <div class="space-y-3">
                  <div>
                    <div class="flex justify-between text-sm mb-1">
                      <span class="text-slate-600">Demands Processed</span>
                      <span class="font-medium">8 / Unlimited</span>
                    </div>
                    <UiProgressBar :value="8" color="blue" size="sm" :show-label="false" />
                  </div>
                  
                  <div>
                    <div class="flex justify-between text-sm mb-1">
                      <span class="text-slate-600">Support Tickets</span>
                      <span class="font-medium">2 / Unlimited</span>
                    </div>
                    <UiProgressBar :value="2" color="green" size="sm" :show-label="false" />
                  </div>
                </div>
              </div>
            </div>

            <div class="flex space-x-3 pt-4">
              <ActionButton variant="primary" label="Change Plan" @click="showPlansModal = true" />
              
              <ActionButton type="cancel" variant="ghost" label="Cancel Subscription" @click="cancelSubscription" />
            </div>
          </div>
        </UiCard>

        <!-- Billing Information -->
        <UiCard>
          <template #header>
            <div class="flex items-center justify-between">
              <h3 class="text-lg font-semibold text-slate-800">
                Billing Information
              </h3>
              <ActionButton type="edit" variant="ghost" size="sm" label="Edit" @click="editingBilling = !editingBilling" />
            </div>
          </template>

          <div v-if="editingBilling" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <UiInput
                v-model="billingForm.cardNumber"
                label="Card Number"
                placeholder="**** **** **** 1234"
              />
              
              <UiInput
                v-model="billingForm.expiryDate"
                label="Expiry Date"
                placeholder="MM/YY"
              />
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <UiInput
                v-model="billingForm.cvv"
                label="CVV"
                placeholder="123"
              />
              
              <UiInput
                v-model="billingForm.nameOnCard"
                label="Name on Card"
                placeholder="John Doe"
              />
            </div>

            <div class="flex justify-end space-x-3 pt-4">
              <ActionButton type="cancel" variant="ghost" label="Cancel" @click="editingBilling = false" />
              <ActionButton type="save" variant="primary" label="Update Billing" @click="updateBilling" />
            </div>
          </div>

          <div v-else class="space-y-4">
            <div class="flex items-center space-x-4 p-4 bg-slate-50 rounded-lg">
              <FaSolidCreditCard class="w-8 h-8 text-slate-600" />
              <div>
                <p class="font-medium text-slate-800">•••• •••• •••• 1234</p>
                <p class="text-sm text-slate-600">Expires 12/25</p>
              </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="text-sm font-medium text-slate-700">Billing Address</label>
                <div class="mt-1 text-sm text-slate-600">
                  <p>123 Main Street</p>
                  <p>New York, NY 10001</p>
                  <p>United States</p>
                </div>
              </div>
              
              <div>
                <label class="text-sm font-medium text-slate-700">Next Billing Date</label>
                <p class="mt-1 text-sm text-slate-800">March 15, 2024</p>
              </div>
            </div>
          </div>
        </UiCard>

        <!-- Billing History -->
        <UiCard>
          <template #header>
            <h3 class="text-lg font-semibold text-slate-800">
              Billing History
            </h3>
          </template>

          <div class="space-y-3">
            <div
              v-for="invoice in billingHistory"
              :key="invoice.id"
              class="flex items-center justify-between py-3 border-b border-slate-100 last:border-b-0"
            >
              <div class="flex items-center space-x-3">
                <div class="w-10 h-10 rounded-lg bg-green-100 flex items-center justify-center">
                  <FaSolidCheck class="w-5 h-5 text-green-600" />
                </div>
                <div>
                  <p class="font-medium text-slate-800">{{ invoice.description }}</p>
                  <p class="text-sm text-slate-600">{{ formatDate(invoice.date) }}</p>
                </div>
              </div>
              
              <div class="flex items-center space-x-4">
                <span class="font-medium text-slate-800">${{ invoice.amount }}</span>
                <ActionButton type="download" size="sm" variant="ghost" @click="downloadInvoice(invoice.id)" />
              </div>
            </div>
          </div>
        </UiCard>
      </div>

      <!-- Sidebar -->
      <div class="space-y-6">
        <!-- Subscription Status -->
        <UiCard>
          <template #header>
            <h3 class="text-lg font-semibold text-slate-800">
              Account Status
            </h3>
          </template>

          <div class="space-y-3">
            <div class="flex justify-between">
              <span class="text-sm text-slate-600">Status</span>
              <span class="text-sm font-medium text-green-600">Active</span>
            </div>
            
            <div class="flex justify-between">
              <span class="text-sm text-slate-600">Next Billing</span>
              <span class="text-sm font-medium text-slate-800">March 15, 2024</span>
            </div>
            
            <div class="flex justify-between">
              <span class="text-sm text-slate-600">Auto-Renewal</span>
              <span class="text-sm font-medium text-green-600">Enabled</span>
            </div>
          </div>
        </UiCard>

        <!-- Quick Actions -->
        <UiCard>
          <template #header>
            <h3 class="text-lg font-semibold text-slate-800">
              Quick Actions
            </h3>
          </template>

          <div class="space-y-2">
            <ActionButton type="download" variant="ghost" class="w-full justify-start" label="Download Invoices" />

            <ActionButton type="edit" variant="ghost" class="w-full justify-start" label="Update Payment Method" />

            <ActionButton type="help" variant="ghost" class="w-full justify-start" label="Contact Support" />
          </div>
        </UiCard>

        <!-- Available Plans -->
        <UiCard>
          <template #header>
            <h3 class="text-lg font-semibold text-slate-800">
              Available Plans
            </h3>
          </template>

          <div class="space-y-3">
            <div class="p-3 border border-slate-200 rounded-lg">
              <div class="flex justify-between items-center mb-2">
                <h4 class="font-medium text-slate-800">Basic</h4>
                <span class="text-lg font-bold text-slate-600">$19/mo</span>
              </div>
              <p class="text-sm text-slate-600">5 demands per month</p>
            </div>

            <div class="p-3 border-2 border-blue-200 bg-blue-50 rounded-lg">
              <div class="flex justify-between items-center mb-2">
                <h4 class="font-medium text-blue-800">Professional</h4>
                <span class="text-lg font-bold text-blue-600">$49/mo</span>
              </div>
              <p class="text-sm text-blue-600">Unlimited demands</p>
              <div class="mt-2">
                <span class="text-xs bg-blue-200 text-blue-800 px-2 py-1 rounded-full">
                  Current Plan
                </span>
              </div>
            </div>

            <div class="p-3 border border-slate-200 rounded-lg">
              <div class="flex justify-between items-center mb-2">
                <h4 class="font-medium text-slate-800">Enterprise</h4>
                <span class="text-lg font-bold text-slate-600">$99/mo</span>
              </div>
              <p class="text-sm text-slate-600">Advanced features</p>
            </div>
          </div>
        </UiCard>
      </div>
    </div>
  </UiMainLayout>
</template>

<script setup lang="ts">
import { ref, reactive } from 'vue';
import {
  FaSolidCheck,
  FaSolidGear,
  FaSolidXmark,
  FaSolidPenToSquare,
  FaSolidCreditCard,
  FaSolidDownload,
  FaSolidUser,
} from 'danx-icon';
import { ActionButton } from 'quasar-ui-danx';
import { UiMainLayout, UiCard, UiInput, UiStatusBadge, UiProgressBar } from '../../shared';

const editingBilling = ref(false);
const showPlansModal = ref(false);

const billingForm = reactive({
  cardNumber: '',
  expiryDate: '',
  cvv: '',
  nameOnCard: '',
});

const billingHistory = [
  {
    id: 1,
    description: 'Professional Plan - February 2024',
    date: '2024-02-15T00:00:00Z',
    amount: 49,
  },
  {
    id: 2,
    description: 'Professional Plan - January 2024',
    date: '2024-01-15T00:00:00Z',
    amount: 49,
  },
  {
    id: 3,
    description: 'Professional Plan - December 2023',
    date: '2023-12-15T00:00:00Z',
    amount: 49,
  },
];

const formatDate = (dateString: string) => {
  return new Date(dateString).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  });
};

const updateBilling = () => {
  // TODO: Implement billing update
  console.log('Update billing information');
  editingBilling.value = false;
};

const cancelSubscription = () => {
  if (confirm('Are you sure you want to cancel your subscription? This will take effect at the end of your current billing period.')) {
    // TODO: Implement subscription cancellation
    console.log('Cancel subscription');
  }
};

const downloadInvoice = (invoiceId: number) => {
  // TODO: Implement invoice download
  console.log('Download invoice:', invoiceId);
};
</script>