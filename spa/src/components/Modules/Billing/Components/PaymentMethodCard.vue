<template>
	<div class="payment-method-card">
		<div class="flex items-center space-x-4 p-4 bg-white rounded-lg border border-slate-200 hover:border-slate-300 transition-colors">
			<!-- Card Icon -->
			<div class="flex-shrink-0">
				<div
					class="w-10 h-10 rounded-lg flex items-center justify-center"
					:class="cardBrandClass"
				>
					<CreditCardIcon class="w-6 h-6" :class="cardIconClass" />
				</div>
			</div>

			<!-- Card Details -->
			<div class="flex-1 min-w-0">
				<div class="flex items-center space-x-2">
					<h4 class="text-sm font-medium text-slate-800 truncate">
						{{ cardBrandLabel }} •••• {{ paymentMethod.card_last_four }}
					</h4>
					<span
						v-if="paymentMethod.is_default"
						class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800"
					>
            Default
          </span>
				</div>
				<p class="text-sm text-slate-600 mt-0.5">
					Expires {{ formatExpiry(paymentMethod.card_exp_month, paymentMethod.card_exp_year) }}
				</p>
			</div>

			<!-- Actions -->
			<div v-if="!hideActions" class="flex items-center space-x-2">
				<!-- Set Default Button -->
				<ActionButton
					v-if="!paymentMethod.is_default && !isDeleting"
					type="edit"
					size="sm"
					variant="ghost"
					tooltip="Set as Default"
					:loading="isSettingDefault"
					@click="handleSetDefault"
				/>

				<!-- Delete Button -->
				<ActionButton
					type="delete"
					size="sm"
					variant="ghost"
					tooltip="Delete Payment Method"
					:loading="isDeleting"
					:disabled="paymentMethod.is_default"
					@click="handleDelete"
				/>
			</div>
		</div>

		<!-- Confirmation Dialog -->
		<ConfirmDialog
			v-if="showDeleteConfirm"
			title="Delete Payment Method"
			message="Are you sure you want to delete this payment method? This action cannot be undone."
			confirm-label="Delete"
			confirm-color="red"
			@confirm="confirmDelete"
		/>
	</div>
</template>

<script setup lang="ts">
import { FaSolidCreditCard as CreditCardIcon } from "danx-icon";
import { ActionButton, ConfirmDialog } from "quasar-ui-danx";
import { computed, ref } from "vue";
import { useBillingState } from "../Composables/useBillingState";
import type { PaymentMethod } from "../Types";

const props = defineProps<{
	paymentMethod: PaymentMethod;
	hideActions?: boolean;
}>();

const emit = defineEmits<{
	"set-default": [paymentMethod: PaymentMethod];
	"delete": [paymentMethod: PaymentMethod];
}>();

// State
const showDeleteConfirm = ref(false);
const isSettingDefault = ref(false);
const isDeleting = ref(false);

// Composables
const { setDefaultPaymentMethod, deletePaymentMethod } = useBillingState();

// Computed
const cardBrandLabel = computed(() => {
	const brand = props.paymentMethod.card_brand.toLowerCase();
	switch (brand) {
		case "visa":
			return "Visa";
		case "mastercard":
			return "Mastercard";
		case "amex":
			return "American Express";
		case "discover":
			return "Discover";
		case "diners":
			return "Diners Club";
		case "jcb":
			return "JCB";
		case "unionpay":
			return "UnionPay";
		default:
			return brand.charAt(0).toUpperCase() + brand.slice(1);
	}
});

const cardBrandClass = computed(() => {
	const brand = props.paymentMethod.card_brand.toLowerCase();
	switch (brand) {
		case "visa":
			return "bg-blue-100";
		case "mastercard":
			return "bg-red-100";
		case "amex":
			return "bg-green-100";
		case "discover":
			return "bg-orange-100";
		default:
			return "bg-slate-100";
	}
});

const cardIconClass = computed(() => {
	const brand = props.paymentMethod.card_brand.toLowerCase();
	switch (brand) {
		case "visa":
			return "text-blue-600";
		case "mastercard":
			return "text-red-600";
		case "amex":
			return "text-green-600";
		case "discover":
			return "text-orange-600";
		default:
			return "text-slate-600";
	}
});

// Methods
function formatExpiry(month: number, year: number): string {
	const monthStr = month.toString().padStart(2, "0");
	const yearStr = year.toString().slice(-2);
	return `${monthStr}/${yearStr}`;
}

async function handleSetDefault() {
	if (isSettingDefault.value) return;

	try {
		isSettingDefault.value = true;
		const success = await setDefaultPaymentMethod(props.paymentMethod.id);

		if (success) {
			emit("set-default", props.paymentMethod);
			console.log("Payment method set as default");
		}

	} catch (error) {
		console.error("Failed to set default payment method:", error);
	} finally {
		isSettingDefault.value = false;
	}
}

function handleDelete() {
	if (props.paymentMethod.is_default) {
		// Cannot delete default payment method
		return;
	}
	showDeleteConfirm.value = true;
}

async function confirmDelete() {
	try {
		isDeleting.value = true;
		const success = await deletePaymentMethod(props.paymentMethod.id);

		if (success) {
			emit("delete", props.paymentMethod);
			console.log("Payment method deleted");
		}

	} catch (error) {
		console.error("Failed to delete payment method:", error);
	} finally {
		isDeleting.value = false;
		showDeleteConfirm.value = false;
	}
}
</script>
