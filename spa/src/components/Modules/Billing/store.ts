import { ref } from "vue";
import type { BillingState, Subscription, SubscriptionPlan, PaymentMethod, BillingHistory, UsageStatistics } from "./Types";

// Store state using Vue 3 reactivity
export const billingStore = ref<BillingState>({
  subscription: null,
  subscriptionPlans: [],
  paymentMethods: [],
  billingHistory: [],
  usage: null,
  isLoading: false,
  error: null
});

// Helper functions for updating store
export function setBillingLoading(loading: boolean) {
  billingStore.value.isLoading = loading;
}

export function setBillingError(error: string | null) {
  billingStore.value.error = error;
}

export function setSubscription(subscription: Subscription | null) {
  billingStore.value.subscription = subscription;
}

export function setSubscriptionPlans(plans: SubscriptionPlan[]) {
  billingStore.value.subscriptionPlans = plans;
}

export function setPaymentMethods(methods: PaymentMethod[]) {
  billingStore.value.paymentMethods = methods;
}

export function addPaymentMethod(method: PaymentMethod) {
  billingStore.value.paymentMethods.push(method);
}

export function updatePaymentMethod(updatedMethod: PaymentMethod) {
  const index = billingStore.value.paymentMethods.findIndex(m => m.id === updatedMethod.id);
  if (index >= 0) {
    billingStore.value.paymentMethods[index] = updatedMethod;
  }
}

export function removePaymentMethod(methodId: string) {
  billingStore.value.paymentMethods = billingStore.value.paymentMethods.filter(m => m.id !== methodId);
}

export function setBillingHistory(history: BillingHistory[]) {
  billingStore.value.billingHistory = history;
}

export function setUsage(usage: UsageStatistics | null) {
  billingStore.value.usage = usage;
}