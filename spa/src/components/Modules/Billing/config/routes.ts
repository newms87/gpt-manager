import { request } from "quasar-ui-danx";
import type {
  BillingAddress,
  BillingHistory,
  PaymentMethod,
  SetupIntent,
  Subscription,
  SubscriptionPlan,
  UsageStatistics
} from "../Types";

export const routes = {
	// Subscription Plans
	subscriptionPlans: {
		list: () => request.get<SubscriptionPlan[]>("subscription-plans")
	},

	// Subscriptions
	subscription: {
		get: () => request.get<Subscription>("billing/subscription"),
		create: (planId: string) => request.post<Subscription>("billing/subscription", { subscription_plan_id: planId }),
		update: (planId: string) => request.put<Subscription>("billing/subscription", { subscription_plan_id: planId }),
		cancel: () => request.delete<void>("billing/subscription")
	},

	// Payment Methods
	paymentMethods: {
		list: () => request.get<PaymentMethod[]>("billing/payment-methods"),
		create: (paymentMethodId: string, billingAddress?: BillingAddress) =>
				request.post<PaymentMethod>("billing/payment-methods", {
					stripe_payment_method_id: paymentMethodId,
					billing_address: billingAddress
				}),
		setDefault: (id: string) => request.put<PaymentMethod>(`billing/payment-methods/${id}/default`),
		delete: (id: string) => request.delete<void>(`billing/payment-methods/${id}`)
	},

	// Setup Intents
	setupIntent: {
		create: () => request.post<SetupIntent>("billing/setup-intent"),
		confirm: (setupIntentId: string, paymentMethodId: string) =>
				request.post<PaymentMethod>("billing/confirm-setup", {
					setup_intent_id: setupIntentId,
					payment_method_id: paymentMethodId
				})
	},

	// Billing History
	billingHistory: {
		list: (params?: { page?: number; per_page?: number }) =>
				request.get<{ data: BillingHistory[]; meta: any }>("billing/history", { params })
	},

	// Usage Statistics
	usage: {
		get: () => request.get<UsageStatistics>("billing/usage")
	}
};
