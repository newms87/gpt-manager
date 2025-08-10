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

		<!-- Loading State -->
		<div v-if="isLoading && !subscription && !error" class="flex items-center justify-center py-12">
			<div class="flex items-center space-x-3">
				<div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
				<span class="text-slate-600">Loading subscription details...</span>
			</div>
		</div>

		<!-- Error State -->
		<div v-else-if="error" class="mx-6 mb-6">
			<div class="p-4 bg-red-50 border border-red-200 rounded-lg">
				<div class="flex items-start space-x-3">
					<FaSolidTriangleExclamation class="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" />
					<div>
						<h3 class="text-sm font-medium text-red-800">Error Loading Billing Information</h3>
						<p class="text-sm text-red-700 mt-1">{{ error }}</p>
						<ActionButton
							variant="outline"
							color="red"
							size="sm"
							class="mt-3"
							label="Retry"
							@click="loadBillingData"
						/>
					</div>
				</div>
			</div>
		</div>

		<!-- Main Content -->
		<div v-else class="grid grid-cols-1 lg:grid-cols-3 gap-6">
			<!-- Left Column -->
			<div class="lg:col-span-2 space-y-6">

				<!-- Current Subscription -->
				<UiCard v-if="hasActiveSubscription">
					<template #header>
						<div class="flex items-center justify-between">
							<h3 class="text-lg font-semibold text-slate-800">Current Plan</h3>
							<UiStatusBadge :status="subscriptionStatusBadge" size="sm">
								{{ subscriptionStatusLabel }}
							</UiStatusBadge>
						</div>
					</template>

					<div class="space-y-6">
						<!-- Plan Details -->
						<div class="flex items-center justify-between p-4 bg-gradient-to-r from-blue-50 to-purple-50 rounded-lg border border-blue-200">
							<div>
								<h4 class="text-xl font-bold text-slate-800">{{ currentPlan?.name }}</h4>
								<p class="text-slate-600">{{ currentPlan?.description }}</p>
							</div>
							<div class="text-right">
								<p class="text-3xl font-bold text-blue-600">${{ currentPlan?.price }}</p>
								<p class="text-sm text-slate-600">per {{ currentPlan?.interval }}</p>
							</div>
						</div>

						<!-- Features and Usage -->
						<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
							<!-- Plan Features -->
							<div>
								<h5 class="font-semibold text-slate-800 mb-3">Plan Features</h5>
								<ul class="space-y-2">
									<li
										v-for="feature in currentPlan?.features" :key="feature"
										class="flex items-center text-sm text-slate-600"
									>
										<FaSolidCheck class="w-4 h-4 text-green-500 mr-2" />
										{{ feature }}
									</li>
								</ul>
							</div>

							<!-- Usage This Month -->
							<div v-if="usage">
								<h5 class="font-semibold text-slate-800 mb-3">Usage This Month</h5>
								<div class="space-y-3">
									<!-- Demands Processed -->
									<div>
										<div class="flex justify-between text-sm mb-1">
											<span class="text-slate-600">Demands Processed</span>
											<span class="font-medium">
                        {{ usage.demands_processed }}
                        {{ usage.demands_limit ? `/ ${usage.demands_limit}` : "/ Unlimited" }}
                      </span>
										</div>
										<UiProgressBar
											:value="usage.demands_processed"
											:max="usage.demands_limit || 100"
											color="blue"
											size="sm"
											:show-label="false"
										/>
									</div>

									<!-- Support Tickets -->
									<div>
										<div class="flex justify-between text-sm mb-1">
											<span class="text-slate-600">Support Tickets</span>
											<span class="font-medium">
                        {{ usage.support_tickets_used }}
                        {{ usage.support_tickets_limit ? `/ ${usage.support_tickets_limit}` : "/ Unlimited" }}
                      </span>
										</div>
										<UiProgressBar
											:value="usage.support_tickets_used"
											:max="usage.support_tickets_limit || 100"
											color="green"
											size="sm"
											:show-label="false"
										/>
									</div>
								</div>
							</div>
						</div>

						<!-- Subscription Actions -->
						<div class="flex space-x-3 pt-4">
							<ActionButton
								variant="primary"
								label="Change Plan"
								@click="showPlansDialog = true"
							/>

							<ActionButton
								type="cancel"
								variant="ghost"
								label="Cancel Subscription"
								:loading="isLoading"
								@click="handleCancelSubscription"
							/>
						</div>
					</div>
				</UiCard>

				<!-- No Subscription - Plan Selection -->
				<div v-else-if="subscriptionPlans.length > 0" class="space-y-6">
					<UiCard>
						<template #header>
							<h3 class="text-lg font-semibold text-slate-800">Choose Your Plan</h3>
						</template>
						<p class="text-slate-600 mb-6">
							Select a subscription plan to get started with premium features.
						</p>
					</UiCard>

					<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
						<PlanSelectionCard
							v-for="plan in subscriptionPlans"
							:key="plan.id"
							:plan="plan"
							:selected="selectedPlanId === plan.id"
							:disabled="isLoading"
							@select="selectedPlanId = plan.id"
							@subscribe="handleSubscribe"
							@add-payment-method="showPaymentMethodDialog = true"
						/>
					</div>
				</div>

				<!-- Payment Methods -->
				<UiCard v-if="paymentMethods.length > 0 || hasActiveSubscription">
					<template #header>
						<div class="flex items-center justify-between">
							<h3 class="text-lg font-semibold text-slate-800">Payment Methods</h3>
							<ActionButton
								type="create"
								variant="ghost"
								size="sm"
								label="Add Payment Method"
								@click="showPaymentMethodDialog = true"
							/>
						</div>
					</template>

					<div v-if="paymentMethods.length > 0" class="space-y-3">
						<PaymentMethodCard
							v-for="method in paymentMethods"
							:key="method.id"
							:payment-method="method"
							@set-default="onPaymentMethodUpdated"
							@delete="onPaymentMethodUpdated"
						/>
					</div>

					<div v-else class="text-center py-8">
						<FaSolidCreditCard class="w-12 h-12 text-slate-400 mx-auto mb-3" />
						<p class="text-slate-600 mb-4">No payment methods added yet</p>
						<ActionButton
							variant="primary"
							label="Add Payment Method"
							@click="showPaymentMethodDialog = true"
						/>
					</div>
				</UiCard>

				<!-- Billing History -->
				<UiCard v-if="billingHistory.length > 0">
					<template #header>
						<h3 class="text-lg font-semibold text-slate-800">Billing History</h3>
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
									<p class="text-sm text-slate-600">{{ fDate(invoice.created_at) }}</p>
								</div>
							</div>

							<div class="flex items-center space-x-4">
								<span class="font-medium text-slate-800">${{ invoice.amount }}</span>
								<ActionButton
									type="download"
									size="sm"
									variant="ghost"
									:href="invoice.invoice_url"
									target="_blank"
								/>
							</div>
						</div>
					</div>
				</UiCard>
			</div>

			<!-- Right Column -->
			<div class="space-y-6">
				<!-- Account Status -->
				<UiCard>
					<template #header>
						<h3 class="text-lg font-semibold text-slate-800">Account Status</h3>
					</template>

					<div class="space-y-3">
						<div class="flex justify-between">
							<span class="text-sm text-slate-600">Status</span>
							<span class="text-sm font-medium" :class="subscriptionStatusClass">
                {{ subscriptionStatusLabel }}
              </span>
						</div>

						<div v-if="subscription?.current_period_end" class="flex justify-between">
							<span class="text-sm text-slate-600">Next Billing</span>
							<span class="text-sm font-medium text-slate-800">
                {{ fDate(subscription.current_period_end) }}
              </span>
						</div>

						<div v-if="hasActiveSubscription" class="flex justify-between">
							<span class="text-sm text-slate-600">Auto-Renewal</span>
							<span class="text-sm font-medium text-green-600">Enabled</span>
						</div>
					</div>
				</UiCard>

				<!-- Available Plans (when user has subscription) -->
				<UiCard v-if="hasActiveSubscription && subscriptionPlans.length > 0">
					<template #header>
						<div class="flex items-center justify-between">
							<h3 class="text-lg font-semibold text-slate-800">Available Plans</h3>
							<ActionButton
								variant="ghost"
								size="sm"
								label="Change Plan"
								@click="showPlansDialog = true"
							/>
						</div>
					</template>

					<div class="space-y-3">
						<div
							v-for="plan in subscriptionPlans" :key="plan.id"
							class="p-3 border rounded-lg"
							:class="{
                   'border-blue-200 bg-blue-50': plan.id === currentPlan?.id,
                   'border-slate-200': plan.id !== currentPlan?.id
                 }"
						>
							<div class="flex justify-between items-center mb-2">
								<h4
									class="font-medium"
									:class="{
                      'text-blue-800': plan.id === currentPlan?.id,
                      'text-slate-800': plan.id !== currentPlan?.id
                    }"
								>
									{{ plan.name }}
								</h4>
								<span
									class="text-lg font-bold"
									:class="{
                        'text-blue-600': plan.id === currentPlan?.id,
                        'text-slate-600': plan.id !== currentPlan?.id
                      }"
								>
                  ${{ plan.price }}/{{ plan.interval === "month" ? "mo" : "yr" }}
                </span>
							</div>
							<p
								class="text-sm"
								:class="{
                   'text-blue-600': plan.id === currentPlan?.id,
                   'text-slate-600': plan.id !== currentPlan?.id
                 }"
							>
								{{ plan.description }}
							</p>
							<div v-if="plan.id === currentPlan?.id" class="mt-2">
                <span class="text-xs bg-blue-200 text-blue-800 px-2 py-1 rounded-full">
                  Current Plan
                </span>
							</div>
						</div>
					</div>
				</UiCard>
			</div>
		</div>

		<!-- Plan Selection Dialog -->
		<ConfirmDialog
			v-if="showPlansDialog"
			title="Change Subscription Plan"
			:show-cancel="false"
			:show-confirm="false"
			max-width="800px"
		>
			<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
				<PlanSelectionCard
					v-for="plan in subscriptionPlans"
					:key="plan.id"
					:plan="plan"
					:selected="selectedPlanId === plan.id"
					:disabled="isLoading"
					@select="selectedPlanId = plan.id"
					@change-plan="handleChangePlan"
				/>
			</div>
		</ConfirmDialog>

		<!-- Add Payment Method Dialog -->
		<ConfirmDialog
			v-if="showPaymentMethodDialog"
			title="Add Payment Method"
			confirm-label="Add Payment Method"
			:confirm-loading="isAddingPaymentMethod"
			:can-confirm="canAddPaymentMethod"
			@confirm="handleAddPaymentMethod"
		>
			<div class="space-y-6">
				<!-- Payment Element -->
				<div v-if="setupIntent?.client_secret">
					<h4 class="text-sm font-medium text-slate-800 mb-3">Payment Information</h4>
					<PaymentElementForm
						:client-secret="setupIntent.client_secret"
						@ready="paymentElementReady = true"
						@error="onPaymentElementError"
					/>
				</div>

				<!-- Billing Address -->
				<div>
					<h4 class="text-sm font-medium text-slate-800 mb-3">Billing Address</h4>
					<BillingAddressForm
						v-model="billingAddress"
						@validate="billingAddressValid = $event"
					/>
				</div>
			</div>
		</ConfirmDialog>
	</UiMainLayout>
</template>

<script setup lang="ts">
import { FaSolidCheck, FaSolidCreditCard, FaSolidTriangleExclamation } from "danx-icon";
import { ActionButton, ConfirmDialog, fDate } from "quasar-ui-danx";
import { computed, onMounted, ref } from "vue";
import {
	BillingAddressForm,
	PaymentElementForm,
	PaymentMethodCard,
	PlanSelectionCard,
	useBillingState,
	usePaymentSetup
} from "../../../components/Modules/Billing";
import type { BillingAddress, SubscriptionPlan } from "../../../components/Modules/Billing/Types";
import { UiCard, UiMainLayout, UiProgressBar, UiStatusBadge } from "../../shared";

// State
const showPlansDialog = ref(false);
const showPaymentMethodDialog = ref(false);
const selectedPlanId = ref<string | null>(null);
const paymentElementReady = ref(false);
const billingAddressValid = ref(false);
const billingAddress = ref<BillingAddress>({
	line1: "",
	line2: "",
	city: "",
	state: "",
	postal_code: "",
	country: "US"
});

// Composables
const {
	subscription,
	subscriptionPlans,
	paymentMethods,
	billingHistory,
	usage,
	isLoading,
	error,
	hasActiveSubscription,
	currentPlan,
	loadAllBillingData,
	createSubscription,
	updateSubscription,
	cancelSubscription,
	clearError
} = useBillingState();

const {
	setupIntent,
	isProcessing: isAddingPaymentMethod,
	setupPaymentMethod,
	createSetupIntent,
	reset: resetPaymentSetup
} = usePaymentSetup();

// Computed
const subscriptionStatusLabel = computed(() => {
	if (!subscription.value) return "No Subscription";

	switch (subscription.value.status) {
		case "active":
			return "Active";
		case "canceled":
			return "Canceled";
		case "incomplete":
			return "Payment Required";
		case "past_due":
			return "Payment Overdue";
		case "trialing":
			return "Trial";
		default:
			return subscription.value.status;
	}
});

const subscriptionStatusBadge = computed(() => {
	if (!subscription.value) return "pending";

	switch (subscription.value.status) {
		case "active":
			return "completed";
		case "trialing":
			return "processing";
		case "canceled":
			return "failed";
		default:
			return "pending";
	}
});

const subscriptionStatusClass = computed(() => {
	if (!subscription.value) return "text-slate-600";

	switch (subscription.value.status) {
		case "active":
			return "text-green-600";
		case "trialing":
			return "text-blue-600";
		case "canceled":
			return "text-red-600";
		case "past_due":
			return "text-amber-600";
		default:
			return "text-slate-600";
	}
});

const canAddPaymentMethod = computed(() =>
	paymentElementReady.value && billingAddressValid.value && !isAddingPaymentMethod.value
);

// Methods
async function loadBillingData() {
	clearError();
	await loadAllBillingData();
}

async function handleSubscribe(plan: SubscriptionPlan) {
	selectedPlanId.value = plan.id;
	const success = await createSubscription(plan.id);
	if (success) {
		selectedPlanId.value = null;
	}
}

async function handleChangePlan(plan: SubscriptionPlan) {
	selectedPlanId.value = plan.id;
	const success = await updateSubscription(plan.id);
	if (success) {
		selectedPlanId.value = null;
		showPlansDialog.value = false;
	}
}

async function handleCancelSubscription() {
	if (!confirm("Are you sure you want to cancel your subscription? This will take effect at the end of your current billing period.")) {
		return;
	}

	const success = await cancelSubscription();
	if (success) {
		console.log("Subscription canceled successfully");
	}
}

async function handleAddPaymentMethod() {
	try {
		const paymentMethod = await setupPaymentMethod(billingAddress.value);

		if (paymentMethod) {
			showPaymentMethodDialog.value = false;
			resetPaymentSetup();
			resetPaymentMethodForm();
			console.log("Payment method added successfully");
		}
	} catch (error) {
		console.error("Failed to add payment method:", error);
	}
}

function onPaymentMethodUpdated() {
	// Payment method store is automatically updated via composable
	console.log("Payment method updated");
}

function onPaymentElementError(error: string) {
	console.error("Payment element error:", error);
}

async function openPaymentMethodDialog() {
	showPaymentMethodDialog.value = true;

	// Create setup intent when dialog opens
	await createSetupIntent();
}

function resetPaymentMethodForm() {
	paymentElementReady.value = false;
	billingAddressValid.value = false;
	billingAddress.value = {
		line1: "",
		line2: "",
		city: "",
		state: "",
		postal_code: "",
		country: "US"
	};
}

// Watch for payment method dialog
function handlePaymentMethodDialogChange(isOpen: boolean) {
	if (isOpen) {
		openPaymentMethodDialog();
	} else {
		resetPaymentSetup();
		resetPaymentMethodForm();
	}
}

// Lifecycle
onMounted(async () => {
	await loadBillingData();
});
</script>
