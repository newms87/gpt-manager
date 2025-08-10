import { ActionOptions } from "quasar-ui-danx";
import { 
  FaSolidCreditCard as PaymentIcon, 
  FaSolidTrash as DeleteIcon,
  FaSolidCheck as ConfirmIcon,
  FaSolidX as CancelIcon,
  FaSolidDownload as DownloadIcon,
  FaSolidPlus as CreateIcon
} from "danx-icon";

export const actions = {
  // Payment Method Actions
  createPaymentMethod: {
    name: "create-payment-method",
    label: "Add Payment Method",
    icon: CreateIcon,
    color: "blue",
    iconClass: "w-4",
    optimistic: false
  } as ActionOptions,

  setDefaultPaymentMethod: {
    name: "set-default-payment-method", 
    label: "Set as Default",
    icon: ConfirmIcon,
    color: "green",
    iconClass: "w-4",
    optimistic: true
  } as ActionOptions,

  deletePaymentMethod: {
    name: "delete-payment-method",
    label: "Delete Payment Method",
    icon: DeleteIcon,
    color: "red", 
    iconClass: "w-4",
    optimistic: false,
    confirm: true
  } as ActionOptions,

  // Subscription Actions
  createSubscription: {
    name: "create-subscription",
    label: "Subscribe",
    icon: PaymentIcon,
    color: "blue",
    iconClass: "w-4", 
    optimistic: false
  } as ActionOptions,

  updateSubscription: {
    name: "update-subscription",
    label: "Change Plan",
    icon: PaymentIcon,
    color: "blue",
    iconClass: "w-4",
    optimistic: false
  } as ActionOptions,

  cancelSubscription: {
    name: "cancel-subscription",
    label: "Cancel Subscription",
    icon: CancelIcon,
    color: "red",
    iconClass: "w-4",
    optimistic: false,
    confirm: true
  } as ActionOptions,

  // Billing History Actions
  downloadInvoice: {
    name: "download-invoice", 
    label: "Download Invoice",
    icon: DownloadIcon,
    color: "blue",
    iconClass: "w-4",
    optimistic: false
  } as ActionOptions
};