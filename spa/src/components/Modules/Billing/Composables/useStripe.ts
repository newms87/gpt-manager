import { ref, onUnmounted } from "vue";
import type { 
  StripeInstance, 
  StripeElements, 
  StripePaymentElement,
  StripeElementsOptions,
  StripeConfig
} from "../Types";

const stripeInstance = ref<StripeInstance | null>(null);
const stripeElements = ref<StripeElements | null>(null);
const paymentElement = ref<StripePaymentElement | null>(null);
const isStripeLoaded = ref(false);
const stripeError = ref<string | null>(null);

// Stripe configuration - mock mode for development
const stripeConfig: StripeConfig = {
  publicKey: 'pk_test_mock_key_for_development', // Mock key for development
  isMockMode: true // Set to false when using real Stripe
};

export function useStripe() {
  
  async function loadStripe(): Promise<boolean> {
    try {
      if (stripeConfig.isMockMode) {
        // Mock Stripe for development
        console.log('Using mock Stripe for development');
        createMockStripe();
        isStripeLoaded.value = true;
        return true;
      }

      // Load real Stripe (would be used in production)
      if (!window.Stripe) {
        const script = document.createElement('script');
        script.src = 'https://js.stripe.com/v3/';
        script.async = true;
        
        await new Promise((resolve, reject) => {
          script.onload = resolve;
          script.onerror = reject;
          document.head.appendChild(script);
        });
      }

      stripeInstance.value = window.Stripe(stripeConfig.publicKey);
      isStripeLoaded.value = true;
      return true;
      
    } catch (error) {
      console.error('Failed to load Stripe:', error);
      stripeError.value = 'Failed to load payment system';
      return false;
    }
  }

  function createMockStripe() {
    // Create mock Stripe instance for development
    stripeInstance.value = {
      elements: (options?: StripeElementsOptions) => {
        return createMockElements(options);
      },
      createPaymentMethod: async (options: any) => {
        // Mock payment method creation
        console.log('Mock: Creating payment method', options);
        await new Promise(resolve => setTimeout(resolve, 1000));
        return {
          paymentMethod: {
            id: 'pm_mock_' + Date.now(),
            type: 'card',
            card: {
              brand: 'visa',
              last4: '4242',
              exp_month: 12,
              exp_year: 2025
            }
          }
        };
      },
      confirmCardSetup: async (clientSecret: string, options?: any) => {
        // Mock setup confirmation
        console.log('Mock: Confirming card setup', clientSecret, options);
        await new Promise(resolve => setTimeout(resolve, 1500));
        return {
          setupIntent: {
            id: 'seti_mock_' + Date.now(),
            status: 'succeeded',
            payment_method: {
              id: 'pm_mock_' + Date.now(),
              type: 'card',
              card: {
                brand: 'visa',
                last4: '4242'
              }
            }
          }
        };
      },
      confirmCardPayment: async (clientSecret: string, options?: any) => {
        console.log('Mock: Confirming card payment', clientSecret, options);
        await new Promise(resolve => setTimeout(resolve, 1500));
        return {
          paymentIntent: {
            id: 'pi_mock_' + Date.now(),
            status: 'succeeded'
          }
        };
      }
    };
  }

  function createMockElements(options?: StripeElementsOptions): StripeElements {
    return {
      create: (type: string, elementOptions?: any) => {
        if (type === 'payment') {
          return createMockPaymentElement();
        }
        return null;
      },
      getElement: (type: string) => {
        if (type === 'payment') {
          return paymentElement.value;
        }
        return null;
      }
    };
  }

  function createMockPaymentElement(): StripePaymentElement {
    const mockElement = {
      mount: (domElement: string | HTMLElement) => {
        console.log('Mock: Mounting payment element to', domElement);
        const element = typeof domElement === 'string' 
          ? document.querySelector(domElement)
          : domElement;
        
        if (element) {
          element.innerHTML = `
            <div class="p-4 border-2 border-dashed border-gray-300 rounded-lg bg-gray-50">
              <div class="text-center text-gray-600">
                <p class="font-medium">Mock Payment Element</p>
                <p class="text-sm mt-1">Development mode - no real card required</p>
                <div class="mt-3 p-2 bg-white rounded border text-left">
                  <div class="text-xs text-gray-500 mb-1">Card Number</div>
                  <div class="font-mono">4242 4242 4242 4242</div>
                  <div class="flex mt-2">
                    <div class="flex-1 pr-2">
                      <div class="text-xs text-gray-500 mb-1">Expiry</div>
                      <div class="font-mono">12/25</div>
                    </div>
                    <div class="flex-1 pl-2">
                      <div class="text-xs text-gray-500 mb-1">CVC</div>
                      <div class="font-mono">123</div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          `;
        }
      },
      unmount: () => {
        console.log('Mock: Unmounting payment element');
      },
      on: (event: string, callback: Function) => {
        console.log('Mock: Adding event listener for', event);
        // Simulate ready event
        if (event === 'ready') {
          setTimeout(() => callback(), 100);
        }
        // Simulate change events
        if (event === 'change') {
          setTimeout(() => callback({ complete: true, empty: false }), 200);
        }
      },
      focus: () => console.log('Mock: Focusing payment element'),
      blur: () => console.log('Mock: Blurring payment element'),
      clear: () => console.log('Mock: Clearing payment element'),
      collapse: () => console.log('Mock: Collapsing payment element'),
      destroy: () => console.log('Mock: Destroying payment element')
    };

    paymentElement.value = mockElement;
    return mockElement;
  }

  function createElements(options: StripeElementsOptions): StripeElements | null {
    if (!stripeInstance.value) {
      console.error('Stripe not loaded');
      return null;
    }

    stripeElements.value = stripeInstance.value.elements(options);
    return stripeElements.value;
  }

  function createPaymentElement(elements: StripeElements, options?: any): StripePaymentElement | null {
    if (!elements) return null;
    
    paymentElement.value = elements.create('payment', options);
    return paymentElement.value;
  }

  async function confirmSetup(clientSecret: string, options?: any) {
    if (!stripeInstance.value) {
      throw new Error('Stripe not initialized');
    }

    return await stripeInstance.value.confirmCardSetup(clientSecret, options);
  }

  async function confirmPayment(clientSecret: string, options?: any) {
    if (!stripeInstance.value) {
      throw new Error('Stripe not initialized');
    }

    return await stripeInstance.value.confirmCardPayment(clientSecret, options);
  }

  // Cleanup on unmount
  onUnmounted(() => {
    if (paymentElement.value) {
      paymentElement.value.destroy();
    }
  });

  return {
    stripeInstance,
    stripeElements,
    paymentElement,
    isStripeLoaded,
    stripeError,
    stripeConfig,
    loadStripe,
    createElements,
    createPaymentElement,
    confirmSetup,
    confirmPayment
  };
}