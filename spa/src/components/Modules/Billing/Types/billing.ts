export interface SubscriptionPlan {
  id: string;
  name: string;
  description: string;
  price: number;
  interval: 'month' | 'year';
  features: string[];
  is_active: boolean;
  stripe_product_id: string | null;
  stripe_price_id: string | null;
  created_at: string;
  updated_at: string;
}

export interface Subscription {
  id: string;
  team_id: string;
  subscription_plan_id: string;
  status: 'active' | 'canceled' | 'incomplete' | 'incomplete_expired' | 'past_due' | 'unpaid' | 'trialing';
  stripe_subscription_id: string | null;
  stripe_customer_id: string | null;
  current_period_start: string | null;
  current_period_end: string | null;
  trial_start: string | null;
  trial_end: string | null;
  canceled_at: string | null;
  ended_at: string | null;
  created_at: string;
  updated_at: string;
  subscription_plan?: SubscriptionPlan;
}

export interface PaymentMethod {
  id: string;
  team_id: string;
  stripe_payment_method_id: string;
  type: 'card';
  card_brand: string;
  card_last_four: string;
  card_exp_month: number;
  card_exp_year: number;
  is_default: boolean;
  created_at: string;
  updated_at: string;
}

export interface BillingAddress {
  line1: string;
  line2?: string;
  city: string;
  state: string;
  postal_code: string;
  country: string;
}

export interface BillingHistory {
  id: string;
  team_id: string;
  subscription_id: string;
  amount: number;
  currency: 'usd';
  status: 'paid' | 'pending' | 'failed';
  invoice_number: string;
  invoice_url: string | null;
  description: string;
  period_start: string;
  period_end: string;
  created_at: string;
  updated_at: string;
}

export interface UsageStatistics {
  current_period_start: string;
  current_period_end: string;
  demands_processed: number;
  demands_limit: number | null; // null means unlimited
  support_tickets_used: number;
  support_tickets_limit: number | null; // null means unlimited
  storage_used_gb: number;
  storage_limit_gb: number | null; // null means unlimited
}

export interface SetupIntent {
  client_secret: string;
  payment_method?: PaymentMethod;
}

export interface BillingState {
  subscription: Subscription | null;
  subscriptionPlans: SubscriptionPlan[];
  paymentMethods: PaymentMethod[];
  billingHistory: BillingHistory[];
  usage: UsageStatistics | null;
  isLoading: boolean;
  error: string | null;
}