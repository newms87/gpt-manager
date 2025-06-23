import { usePusher } from "@/helpers/pusher";
import { WhatsAppMessage } from "@/types/whatsapp";
import { storeObject } from "quasar-ui-danx";
import { Notify } from "quasar";

export function useWhatsAppStore() {
	const pusher = usePusher();

	function setupWhatsAppListeners() {
		if (!pusher) return;

		// Listen for new WhatsApp messages
		pusher.onEvent('WhatsAppMessageReceived', 'WhatsAppMessageReceived', (data) => {
			const message = data.message as WhatsAppMessage;
			
			// Store the message to update all components using it
			storeObject(message);
			
			// Show notification for new inbound messages
			if (message.direction === 'inbound') {
				Notify.create({
					type: 'info',
					message: `New WhatsApp message from ${message.formatted_from_number}`,
					caption: message.message.length > 50 
						? message.message.substring(0, 50) + '...'
						: message.message,
					timeout: 5000,
					actions: [
						{
							icon: 'close',
							color: 'white',
							round: true,
							handler: () => { /* notification will auto-close */ }
						}
					]
				});
			}
		});

		// Listen for message status updates
		pusher.onEvent('WhatsAppMessageUpdated', 'WhatsAppMessageUpdated', (data) => {
			const message = data.message as WhatsAppMessage;
			storeObject(message);
		});
	}

	return {
		setupWhatsAppListeners
	};
}