import { AuditRequestController } from "@/components/Modules/Audits/auditRequestControls";
import { fDate, fNumber } from "quasar-ui-danx";
import { TableColumn } from "quasar-ui-danx/types";

export const columns: TableColumn[] = [
	{
		name: "id",
		label: "ID",
		align: "left",
		sortable: true,
		required: true,
		onClick: (target) => AuditRequestController.activatePanel(target, "request")
	},
	{
		name: "user_name",
		label: "User",
		sortable: true,
		align: "left"
	},
	{
		name: "created_at",
		label: "Created Date",
		sortable: true,
		align: "left",
		format: fDate
	},
	{
		name: "http_method",
		label: "HTTP Method",
		sortable: true,
		sortBy: "request",
		sortByExpression: "request->>'$.method'",
		align: "left"
	},
	{
		name: "http_status_code",
		label: "HTTP Status",
		sortable: true,
		sortBy: "response",
		sortByExpression: "response->>'$.status'",
		align: "left"
	},
	{
		name: "url",
		label: "URL",
		sortable: true,
		align: "left"
	},
	{
		name: "response_length",
		label: "Response Length",
		format: v => fNumber(v) + " B",
		sortable: true,
		sortBy: "response",
		sortByExpression: "CONVERT(response->>'$.length', UNSIGNED)",
		align: "right"
	},
	{
		name: "max_memory",
		label: "Max Memory",
		format: v => fNumber(v) + " B",
		sortable: true,
		sortBy: "response",
		sortByExpression: "CONVERT(response->>'$.max_memory_used', UNSIGNED)",
		align: "right"
	},
	{
		name: "time",
		label: "Timing",
		sortable: true,
		align: "left",
		format: fNumber
	},
	{
		name: "environment",
		label: "Environment",
		sortable: true,
		align: "left"
	},
	{
		name: "session_id",
		label: "Session",
		sortable: true,
		align: "left"
	}
];
