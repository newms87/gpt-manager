import { ref, computed } from "vue";
import { useStripe } from "./useStripe";
import { routes } from "../config/routes";
import { addPaymentMethod } from "../store";
import type { SetupIntent, BillingAddress, PaymentMethod } from "../Types";

export function usePaymentSetup() {
  const isProcessing = ref(false);
  const setupError = ref<string | null>(null);
  const setupIntent = ref<SetupIntent | null>(null);
  
  const { 
    stripeInstance, 
    stripeElements, 
    isStripeLoaded, 
    confirmSetup 
  } = useStripe();

  const canProcessPayment = computed(() => 
    isStripeLoaded.value && stripeElements.value && !isProcessing.value
  );

  async function createSetupIntent(): Promise<SetupIntent | null> {
    try {
      setupError.value = null;
      isProcessing.value = true;

      const response = await routes.setupIntent.create();
      setupIntent.value = response.data;
      
      return setupIntent.value;
      
    } catch (error: any) {
      const errorMessage = error.response?.data?.message || error.message || 'Failed to create setup intent';
      setupError.value = errorMessage;
      console.error('Setup intent creation failed:', error);
      return null;
    } finally {
      isProcessing.value = false;
    }
  }

  async function confirmPaymentSetup(
    billingAddress?: BillingAddress
  ): Promise<PaymentMethod | null> {
    if (!setupIntent.value?.client_secret) {
      setupError.value = 'No setup intent available';
      return null;
    }

    if (!stripeElements.value) {
      setupError.value = 'Payment form not ready';
      return null;
    }

    try {
      setupError.value = null;
      isProcessing.value = true;

      // Confirm the setup with Stripe
      const confirmParams: any = {
        elements: stripeElements.value
      };

      if (billingAddress) {
        confirmParams.confirmParams = {
          payment_method_data: {
            billing_details: {
              address: {
                line1: billingAddress.line1,
                line2: billingAddress.line2 || null,
                city: billingAddress.city,
                state: billingAddress.state,
                postal_code: billingAddress.postal_code,
                country: billingAddress.country
              }
            }
          }
        };
      }

      const { setupIntent: confirmedSetup, error } = await confirmSetup(
        setupIntent.value.client_secret,
        confirmParams
      );

      if (error) {
        setupError.value = error.message;
        console.error('Setup confirmation failed:', error);
        return null;
      }

      if (!confirmedSetup?.payment_method?.id) {
        setupError.value = 'No payment method received from confirmation';
        return null;
      }

      // Save the payment method to our backend
      const response = await routes.paymentMethods.create(
        confirmedSetup.payment_method.id,
        billingAddress
      );

      const paymentMethod = response.data;
      
      // Update store
      addPaymentMethod(paymentMethod);
      
      return paymentMethod;
      
    } catch (error: any) {
      const errorMessage = error.response?.data?.message || error.message || 'Payment setup failed';
      setupError.value = errorMessage;
      console.error('Payment setup failed:', error);
      return null;
    } finally {
      isProcessing.value = false;
    }
  }

  async function setupPaymentMethod(billingAddress?: BillingAddress): Promise<PaymentMethod | null> {
    // First create the setup intent
    const intent = await createSetupIntent();
    if (!intent) {
      return null;
    }

    // Then confirm the setup
    return await confirmPaymentSetup(billingAddress);
  }

  function clearSetupError() {
    setupError.value = null;
  }

  function reset() {
    setupIntent.value = null;
    setupError.value = null;
    isProcessing.value = false;
  }

  return {
    setupIntent,
    setupError,
    isProcessing,
    canProcessPayment,
    createSetupIntent,
    confirmPaymentSetup,
    setupPaymentMethod,
    clearSetupError,
    reset
  };
}