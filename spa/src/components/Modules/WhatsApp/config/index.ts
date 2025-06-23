import { WhatsAppConnection, WhatsAppMessage } from "@/types/whatsapp";
import { DanxController } from "quasar-ui-danx";
import { actionControls, batchActions, menuActions } from "./actions";
import { connectionColumns, messageColumns } from "./columns";
import { connectionControls, messageControls } from "./controls";
import { connectionFields, messageFields } from "./fields";
import { connectionPanels, messagePanels } from "./panels";
import { whatsAppConnectionRoutes, whatsAppMessageRoutes } from "./whatsapp-routes";

export const dxWhatsAppConnection = {
	...connectionControls,
	...actionControls,
	menuActions,
	batchActions,
	columns: connectionColumns,
	fields: connectionFields,
	panels: connectionPanels,
	routes: whatsAppConnectionRoutes
} as DanxController<WhatsAppConnection>;

export const dxWhatsAppMessage = {
	...messageControls,
	columns: messageColumns,
	fields: messageFields,
	panels: messagePanels,
	routes: whatsAppMessageRoutes
} as DanxController<WhatsAppMessage>;