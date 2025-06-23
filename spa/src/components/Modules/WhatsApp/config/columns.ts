import { WhatsAppConnection, WhatsAppMessage } from "@/types/whatsapp";
import { fDate } from "quasar-ui-danx";

export const connectionColumns = [
	{
		name: "name",
		label: "Connection Name",
		align: "left",
		sortable: true,
		onClick: (connection: WhatsAppConnection) => connection // Will be handled by ActionTableLayout
	},
	{
		name: "display_phone_number",
		label: "Phone Number",
		align: "left"
	},
	{
		name: "api_provider",
		label: "Provider",
		align: "center",
		format: (value: string) => value === 'twilio' ? 'Twilio' : 'WhatsApp Business'
	},
	{
		name: "status",
		label: "Status", 
		align: "center",
		format: (value: string, connection: WhatsAppConnection) => ({
			component: "div",
			class: "flex items-center gap-2",
			children: [
				{
					component: "div",
					class: `w-2 h-2 rounded-full ${getStatusColor(connection.status)}`
				},
				{
					component: "span",
					class: `capitalize ${getStatusTextColor(connection.status)}`,
					children: value
				}
			]
		})
	},
	{
		name: "message_count",
		label: "Messages",
		align: "center",
		format: (value: number) => value || 0
	},
	{
		name: "verified_at",
		label: "Last Verified",
		align: "center",
		format: (value: string) => value ? fDate(value, "M/d/yyyy") : "Never"
	}
];

export const messageColumns = [
	{
		name: "direction",
		label: "Direction",
		align: "center",
		format: (value: string) => ({
			component: "div",
			class: "flex items-center gap-2",
			children: [
				{
					component: "div",
					class: `w-2 h-2 rounded-full ${value === 'inbound' ? 'bg-blue-400' : 'bg-green-400'}`
				},
				{
					component: "span",
					class: `capitalize ${value === 'inbound' ? 'text-blue-400' : 'text-green-400'}`,
					children: value
				}
			]
		})
	},
	{
		name: "from_number",
		label: "From",
		align: "left",
		format: (value: string, message: WhatsAppMessage) => message.formatted_from_number
	},
	{
		name: "to_number", 
		label: "To",
		align: "left",
		format: (value: string, message: WhatsAppMessage) => message.formatted_to_number
	},
	{
		name: "message",
		label: "Message",
		align: "left",
		format: (value: string) => ({
			component: "div",
			class: "max-w-xs truncate",
			children: value
		}),
		onClick: (message: WhatsAppMessage) => message // Will be handled by ActionTableLayout
	},
	{
		name: "status",
		label: "Status",
		align: "center",
		format: (value: string) => ({
			component: "span",
			class: `px-2 py-1 rounded text-xs font-medium ${getMessageStatusClass(value)}`,
			children: value.charAt(0).toUpperCase() + value.slice(1)
		})
	},
	{
		name: "has_media",
		label: "Media",
		align: "center",
		format: (value: boolean) => value ? "📎" : ""
	},
	{
		name: "created_at",
		label: "Time",
		align: "center",
		format: (value: string) => fDate(value, 'M/d h:mm A')
	}
];

function getStatusColor(status: string): string {
	switch (status) {
		case 'connected':
			return 'bg-green-400';
		case 'pending':
			return 'bg-yellow-400';
		case 'error':
			return 'bg-red-400';
		default:
			return 'bg-slate-400';
	}
}

function getStatusTextColor(status: string): string {
	switch (status) {
		case 'connected':
			return 'text-green-400';
		case 'pending':
			return 'text-yellow-400';
		case 'error':
			return 'text-red-400';
		default:
			return 'text-slate-400';
	}
}

function getMessageStatusClass(status: string): string {
	switch (status) {
		case 'delivered':
		case 'read':
			return 'bg-green-900 text-green-300';
		case 'sent':
			return 'bg-blue-900 text-blue-300';
		case 'pending':
			return 'bg-yellow-900 text-yellow-300';
		case 'failed':
			return 'bg-red-900 text-red-300';
		default:
			return 'bg-slate-700 text-slate-300';
	}
}