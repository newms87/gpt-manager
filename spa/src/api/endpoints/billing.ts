/**
 * Billing API Endpoints
 *
 * All billing-related API endpoints for managing subscriptions,
 * payments, and billing history.
 */

import { buildApiUrl } from "../config";

export const billing = {
	/**
	 * Subscriptions endpoint
	 * @endpoint /subscriptions
	 */
	subscriptions: buildApiUrl("/subscriptions"),

	/**
	 * Subscription plans endpoint
	 * @endpoint /subscription-plans
	 */
	subscriptionPlans: buildApiUrl("/subscription-plans"),

	/**
	 * Payment methods endpoint
	 * @endpoint /payment-methods
	 */
	paymentMethods: buildApiUrl("/payment-methods"),

	/**
	 * Billing history endpoint
	 * @endpoint /billing-history
	 */
	billingHistory: buildApiUrl("/billing-history"),
} as const;
