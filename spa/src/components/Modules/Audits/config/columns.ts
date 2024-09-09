import { AuditRequest } from "@/components/Modules/Audits/audit-requests";
import { dbDateTime, fNumber, TableColumn } from "quasar-ui-danx";
import { controls } from "./controls";

export const columns: TableColumn<AuditRequest>[] = [
	{
		name: "id",
		label: "ID",
		align: "left",
		sortable: true,
		shrink: true,
		onClick: (target) => controls.activatePanel(target, "request")
	},
	{
		name: "user_name",
		label: "User",
		sortable: true,
		sortBy: "user.name",
		align: "left"
	},
	{
		name: "created_at",
		label: "Created Date",
		sortable: true,
		align: "left",
		format: dbDateTime
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
		name: "audits_count",
		label: "Audits",
		align: "left",
		format: fNumber,
		onClick: (target) => controls.activatePanel(target, "audits")
	},
	{
		name: "api_logs_count",
		label: "API Logs",
		align: "left",
		format: fNumber,
		onClick: (target) => controls.activatePanel(target, "api-logs")
	},
	{
		name: "ran_jobs_count",
		label: "Ran Jobs",
		align: "left",
		format: fNumber,
		onClick: (target) => controls.activatePanel(target, "ran-jobs")
	},
	{
		name: "dispatched_jobs_count",
		label: "Dispatched Jobs",
		align: "left",
		format: fNumber,
		onClick: (target) => controls.activatePanel(target, "dispatched-jobs")
	},
	{
		name: "errors_count",
		label: "Errors",
		align: "left",
		format: fNumber,
		onClick: (target) => controls.activatePanel(target, "errors")
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
