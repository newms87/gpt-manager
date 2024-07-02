import { AuditRequestRoutes } from "@/routes/auditRequestRoutes";
import { useListControls } from "quasar-ui-danx";
import { ActionController } from "quasar-ui-danx";

export const AuditRequestController: ActionController = useListControls("audit-requests", {
	label: "Audit Requests",
	routes: AuditRequestRoutes
});
