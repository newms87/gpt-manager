// Stripe types for frontend integration
export interface StripeCardElement {
  element: any;
  complete: boolean;
  error: any;
}

export interface StripeElements {
  create: (type: string, options?: any) => any;
  getElement: (type: string) => any;
}

export interface StripeInstance {
  elements: (options?: any) => StripeElements;
  createPaymentMethod: (options: any) => Promise<{
    paymentMethod?: any;
    error?: any;
  }>;
  confirmCardPayment: (clientSecret: string, options?: any) => Promise<{
    paymentIntent?: any;
    error?: any;
  }>;
  confirmCardSetup: (clientSecret: string, options?: any) => Promise<{
    setupIntent?: any;
    error?: any;
  }>;
}

export interface StripePaymentElementOptions {
  mode: 'payment' | 'setup' | 'subscription';
  amount?: number;
  currency?: string;
  setup_future_usage?: 'off_session' | 'on_session';
  payment_method_types?: string[];
  appearance?: {
    theme?: 'stripe' | 'night' | 'flat';
    variables?: Record<string, string>;
  };
}

export interface StripeElementsOptions {
  clientSecret: string;
  appearance?: {
    theme?: 'stripe' | 'night' | 'flat';
    variables?: Record<string, string>;
  };
  fonts?: Array<{
    family: string;
    src: string;
    style?: string;
    weight?: string;
  }>;
}

export interface StripePaymentElement {
  mount: (domElement: string | HTMLElement) => void;
  unmount: () => void;
  on: (event: string, callback: Function) => void;
  focus: () => void;
  blur: () => void;
  clear: () => void;
  collapse: () => void;
  destroy: () => void;
}

export interface StripeError {
  type: string;
  code?: string;
  message: string;
  decline_code?: string;
  charge?: string;
  payment_intent?: any;
  payment_method?: any;
  setup_intent?: any;
}

export interface PaymentElementChangeEvent {
  complete: boolean;
  empty: boolean;
  error?: StripeError;
}

export interface ConfirmPaymentOptions {
  elements: StripeElements;
  confirmParams?: {
    return_url?: string;
    payment_method_data?: any;
  };
  redirect?: 'always' | 'if_required';
}

export interface ConfirmSetupOptions {
  elements: StripeElements;
  confirmParams?: {
    return_url?: string;
    payment_method_data?: any;
  };
  redirect?: 'always' | 'if_required';
}

// Environment configuration
export interface StripeConfig {
  publicKey: string;
  isMockMode: boolean;
}