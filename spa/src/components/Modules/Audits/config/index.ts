import { AuditRequest } from "@/components/Modules/Audits/audit-requests";
import { ActionController } from "quasar-ui-danx";
import { columns } from "./columns";
import { controls } from "./controls";
import { filters } from "./filters";
import { panels } from "./panels";
import { routes } from "./routes";

export const dxAudit = {
	...controls,
	columns,
	filters,
	panels,
	routes
} as ActionController<AuditRequest>;
