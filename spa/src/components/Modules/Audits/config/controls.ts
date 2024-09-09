import { ListController, useControls } from "quasar-ui-danx";
import { AuditRequest } from "../audit-requests";
import { routes } from "./routes";

export const controls = useControls("audit-requests", {
	label: "Audit Requests",
	routes
}) as ListController<AuditRequest>;
