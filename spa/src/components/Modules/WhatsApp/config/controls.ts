import { WhatsAppConnection, WhatsAppMessage } from "@/types/whatsapp";
import { ListController, useControls } from "quasar-ui-danx";
import { whatsAppConnectionRoutes, whatsAppMessageRoutes } from "./whatsapp-routes";

export const connectionControls = useControls("whatsapp-connections", {
	label: "WhatsApp Connections",
	routes: whatsAppConnectionRoutes
}) as ListController<WhatsAppConnection>;

export const messageControls = useControls("whatsapp-messages", {
	label: "WhatsApp Messages",
	routes: whatsAppMessageRoutes
}) as ListController<WhatsAppMessage>;