import { routes } from "@/components/Modules/Audits/config/routes";
import { ListController, PagedItems, useControls } from "quasar-ui-danx";
import type { ShallowRef } from "vue";
import { AuditRequest } from "../audit-requests";

export interface AuditRequestPagedItems extends PagedItems {
	data: AuditRequest[];
}

export interface AuditRequestControllerInterface extends ListController {
	activeItem: ShallowRef<AuditRequest>;
	pagedItems: ShallowRef<AuditRequestPagedItems>;
}

export const controls = useControls("audit-requests", {
	label: "Audit Requests",
	routes: routes
}) as AuditRequestControllerInterface;
