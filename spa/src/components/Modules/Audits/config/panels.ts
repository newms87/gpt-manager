import { AuditRequest } from "@/components/Modules/Audits/audit-requests";
import {
	AuditRequestApiLogsPanel,
	AuditRequestAuditsPanel,
	AuditRequestErrorsPanel,
	AuditRequestJobsPanel,
	AuditRequestLogsPanel,
	AuditRequestRequestPanel,
	AuditRequestResponsePanel
} from "@/components/Modules/Audits/Panels";
import { BadgeTab } from "quasar-ui-danx";
import { h } from "vue";

export const panels = [
	{
		name: "request",
		label: "Request",
		class: "w-[80em]",
		vnode: (auditRequest: AuditRequest) => h(AuditRequestRequestPanel, { auditRequest })
	},
	{
		name: "response",
		label: "Response",
		class: "w-[80em]",
		vnode: (auditRequest: AuditRequest) => h(AuditRequestResponsePanel, { auditRequest })
	},
	{
		name: "logs",
		label: "Logs",
		class: "w-[80em]",
		vnode: (auditRequest: AuditRequest) => h(AuditRequestLogsPanel, { auditRequest })
	},
	{
		name: "audits",
		label: "Audits",
		class: "w-[80em]",
		tabVnode: (auditRequest: AuditRequest) => h(BadgeTab, { count: auditRequest.audits_count }),
		vnode: (auditRequest: AuditRequest) => h(AuditRequestAuditsPanel, { auditRequest })
	},
	{
		name: "api-logs",
		label: "API Logs",
		class: "w-[80em]",
		tabVnode: (auditRequest: AuditRequest) => h(BadgeTab, { count: auditRequest.api_logs_count }),
		vnode: (auditRequest: AuditRequest) => h(AuditRequestApiLogsPanel, { auditRequest })
	},
	{
		name: "ran-jobs",
		label: "Ran Jobs",
		class: "w-[80em]",
		tabVnode: (auditRequest: AuditRequest) => h(BadgeTab, { count: auditRequest.ran_jobs_count }),
		vnode: (auditRequest: AuditRequest) => h(AuditRequestJobsPanel, {
			jobs: auditRequest.ran_jobs || []
		})
	},
	{
		name: "dispatched-jobs",
		label: "Dispatched Jobs",
		class: "w-[80em]",
		tabVnode: (auditRequest: AuditRequest) => h(BadgeTab, { count: auditRequest.dispatched_jobs_count }),
		vnode: (auditRequest: AuditRequest) => h(AuditRequestJobsPanel, {
			jobs: auditRequest.dispatched_jobs || []
		})
	},
	{
		name: "errors",
		label: "Errors",
		class: "w-[80em]",
		tabVnode: (auditRequest: AuditRequest) => h(BadgeTab, { count: auditRequest.errors_count }),
		vnode: (auditRequest: AuditRequest) => h(AuditRequestErrorsPanel, { auditRequest })
	}
];
