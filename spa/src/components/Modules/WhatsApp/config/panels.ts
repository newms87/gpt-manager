import WhatsAppConnectionForm from "../WhatsAppConnectionForm.vue";
import WhatsAppConnectionPanel from "../WhatsAppConnectionPanel.vue";
import WhatsAppMessagePanel from "../WhatsAppMessagePanel.vue";

export const connectionPanels = [
	{
		name: "create",
		title: "Create WhatsApp Connection",
		component: WhatsAppConnectionForm
	},
	{
		name: "edit",
		title: "Edit WhatsApp Connection",
		component: WhatsAppConnectionForm
	},
	{
		name: "details",
		title: "WhatsApp Connection Details",
		component: WhatsAppConnectionPanel
	}
];

export const messagePanels = [
	{
		name: "details",
		title: "Message Details",
		component: WhatsAppMessagePanel
	}
];