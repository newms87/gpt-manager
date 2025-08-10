import { computed } from "vue";
import { routes } from "../config/routes";
import { 
  billingStore,
  setBillingLoading,
  setBillingError,
  setSubscription,
  setSubscriptionPlans,
  setPaymentMethods,
  setBillingHistory,
  setUsage,
  updatePaymentMethod,
  removePaymentMethod
} from "../store";
import type { SubscriptionPlan, PaymentMethod, BillingHistory } from "../Types";

export function useBillingState() {
  
  // Computed getters
  const subscription = computed(() => billingStore.value.subscription);
  const subscriptionPlans = computed(() => billingStore.value.subscriptionPlans);
  const paymentMethods = computed(() => billingStore.value.paymentMethods);
  const billingHistory = computed(() => billingStore.value.billingHistory);
  const usage = computed(() => billingStore.value.usage);
  const isLoading = computed(() => billingStore.value.isLoading);
  const error = computed(() => billingStore.value.error);
  
  const defaultPaymentMethod = computed(() => 
    paymentMethods.value.find(method => method.is_default)
  );
  
  const hasActiveSubscription = computed(() => 
    subscription.value?.status === 'active'
  );
  
  const currentPlan = computed(() => {
    if (!subscription.value?.subscription_plan_id) return null;
    return subscriptionPlans.value.find(plan => 
      plan.id === subscription.value?.subscription_plan_id
    );
  });

  // Data loading functions
  async function loadSubscriptionPlans() {
    try {
      setBillingError(null);
      setBillingLoading(true);
      
      const response = await routes.subscriptionPlans.list();
      setSubscriptionPlans(response.data);
      
    } catch (error: any) {
      const errorMessage = error.response?.data?.message || 'Failed to load subscription plans';
      setBillingError(errorMessage);
      console.error('Failed to load subscription plans:', error);
    } finally {
      setBillingLoading(false);
    }
  }

  async function loadSubscription() {
    try {
      setBillingError(null);
      
      const response = await routes.subscription.get();
      setSubscription(response.data);
      
    } catch (error: any) {
      // Not having a subscription is not an error
      if (error.response?.status !== 404) {
        const errorMessage = error.response?.data?.message || 'Failed to load subscription';
        setBillingError(errorMessage);
        console.error('Failed to load subscription:', error);
      }
    }
  }

  async function loadPaymentMethods() {
    try {
      setBillingError(null);
      
      const response = await routes.paymentMethods.list();
      setPaymentMethods(response.data);
      
    } catch (error: any) {
      const errorMessage = error.response?.data?.message || 'Failed to load payment methods';
      setBillingError(errorMessage);
      console.error('Failed to load payment methods:', error);
    }
  }

  async function loadBillingHistory() {
    try {
      setBillingError(null);
      
      const response = await routes.billingHistory.list({ per_page: 20 });
      setBillingHistory(response.data.data);
      
    } catch (error: any) {
      const errorMessage = error.response?.data?.message || 'Failed to load billing history';
      setBillingError(errorMessage);
      console.error('Failed to load billing history:', error);
    }
  }

  async function loadUsageStatistics() {
    try {
      setBillingError(null);
      
      const response = await routes.usage.get();
      setUsage(response.data);
      
    } catch (error: any) {
      const errorMessage = error.response?.data?.message || 'Failed to load usage statistics';
      setBillingError(errorMessage);
      console.error('Failed to load usage statistics:', error);
    }
  }

  async function loadAllBillingData() {
    setBillingLoading(true);
    
    await Promise.all([
      loadSubscriptionPlans(),
      loadSubscription(),
      loadPaymentMethods(),
      loadBillingHistory(),
      loadUsageStatistics()
    ]);
    
    setBillingLoading(false);
  }

  // Subscription management
  async function createSubscription(planId: string): Promise<boolean> {
    try {
      setBillingError(null);
      setBillingLoading(true);
      
      const response = await routes.subscription.create(planId);
      setSubscription(response.data);
      
      // Reload related data
      await Promise.all([
        loadBillingHistory(),
        loadUsageStatistics()
      ]);
      
      return true;
      
    } catch (error: any) {
      const errorMessage = error.response?.data?.message || 'Failed to create subscription';
      setBillingError(errorMessage);
      console.error('Failed to create subscription:', error);
      return false;
    } finally {
      setBillingLoading(false);
    }
  }

  async function updateSubscription(planId: string): Promise<boolean> {
    try {
      setBillingError(null);
      setBillingLoading(true);
      
      const response = await routes.subscription.update(planId);
      setSubscription(response.data);
      
      // Reload related data
      await loadUsageStatistics();
      
      return true;
      
    } catch (error: any) {
      const errorMessage = error.response?.data?.message || 'Failed to update subscription';
      setBillingError(errorMessage);
      console.error('Failed to update subscription:', error);
      return false;
    } finally {
      setBillingLoading(false);
    }
  }

  async function cancelSubscription(): Promise<boolean> {
    try {
      setBillingError(null);
      setBillingLoading(true);
      
      await routes.subscription.cancel();
      setSubscription(null);
      
      return true;
      
    } catch (error: any) {
      const errorMessage = error.response?.data?.message || 'Failed to cancel subscription';
      setBillingError(errorMessage);
      console.error('Failed to cancel subscription:', error);
      return false;
    } finally {
      setBillingLoading(false);
    }
  }

  // Payment method management
  async function setDefaultPaymentMethod(methodId: string): Promise<boolean> {
    try {
      setBillingError(null);
      
      const response = await routes.paymentMethods.setDefault(methodId);
      updatePaymentMethod(response.data);
      
      // Update all other methods to not be default
      paymentMethods.value.forEach(method => {
        if (method.id !== methodId && method.is_default) {
          updatePaymentMethod({ ...method, is_default: false });
        }
      });
      
      return true;
      
    } catch (error: any) {
      const errorMessage = error.response?.data?.message || 'Failed to set default payment method';
      setBillingError(errorMessage);
      console.error('Failed to set default payment method:', error);
      return false;
    }
  }

  async function deletePaymentMethod(methodId: string): Promise<boolean> {
    try {
      setBillingError(null);
      
      await routes.paymentMethods.delete(methodId);
      removePaymentMethod(methodId);
      
      return true;
      
    } catch (error: any) {
      const errorMessage = error.response?.data?.message || 'Failed to delete payment method';
      setBillingError(errorMessage);
      console.error('Failed to delete payment method:', error);
      return false;
    }
  }

  function clearError() {
    setBillingError(null);
  }

  return {
    // State
    subscription,
    subscriptionPlans,
    paymentMethods,
    billingHistory,
    usage,
    isLoading,
    error,
    defaultPaymentMethod,
    hasActiveSubscription,
    currentPlan,
    
    // Actions
    loadSubscriptionPlans,
    loadSubscription,
    loadPaymentMethods,
    loadBillingHistory,
    loadUsageStatistics,
    loadAllBillingData,
    createSubscription,
    updateSubscription,
    cancelSubscription,
    setDefaultPaymentMethod,
    deletePaymentMethod,
    clearError
  };
}