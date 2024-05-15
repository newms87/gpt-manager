import { AuditRequest } from "@/components/Modules/Audits/audit-requests";
import { AuditRequestController } from "@/components/Modules/Audits/auditRequestControls";
import {
	AuditRequestAuditsPanel,
	AuditRequestErrorsPanel,
	AuditRequestJobsPanel,
	AuditRequestRequestPanel,
	AuditRequestResponsePanel
} from "@/components/Modules/Audits/Panels";
import { BadgeTab } from "quasar-ui-danx";
import { ActionPanel } from "quasar-ui-danx/types";
import { computed, h } from "vue";

const activeItem = computed<AuditRequest>(() => AuditRequestController.activeItem.value);

export const panels = computed<ActionPanel[]>(() => [
	{
		name: "request",
		label: "Request",
		class: "w-[80em]",
		vnode: () => h(AuditRequestRequestPanel, {
			auditRequest: activeItem.value
		})
	},
	{
		name: "response",
		label: "Response",
		class: "w-[80em]",
		vnode: () => h(AuditRequestResponsePanel, {
			auditRequest: activeItem.value
		})
	},
	{
		name: "audits",
		label: "Audits",
		class: "w-[80em]",
		tabVnode: () => h(BadgeTab, { count: activeItem.value.audits_count }),
		vnode: () => h(AuditRequestAuditsPanel, {
			auditRequest: activeItem.value
		})
	},
	{
		name: "api-logs",
		label: "API Logs",
		class: "w-[80em]",
		tabVnode: () => h(BadgeTab, { count: activeItem.value.api_logs_count }),
		vnode: () => h(AuditRequestAuditsPanel, {
			auditRequest: activeItem.value
		})
	},
	{
		name: "ran-jobs",
		label: "Ran Jobs",
		class: "w-[80em]",
		tabVnode: () => h(BadgeTab, { count: activeItem.value.ran_jobs_count }),
		vnode: () => h(AuditRequestJobsPanel, {
			auditRequest: activeItem.value,
			jobs: activeItem.value.ran_jobs || []
		})
	},
	{
		name: "dispatched-jobs",
		label: "Dispatched Jobs",
		class: "w-[80em]",
		tabVnode: () => h(BadgeTab, { count: activeItem.value.dispatched_jobs_count }),
		vnode: () => h(AuditRequestJobsPanel, {
			auditRequest: activeItem.value,
			jobs: activeItem.value.dispatched_jobs || []
		})
	},
	{
		name: "errors",
		label: "Errors",
		class: "w-[80em]",
		tabVnode: () => h(BadgeTab, { count: activeItem.value.errors_count }),
		vnode: () => h(AuditRequestErrorsPanel, {
			auditRequest: activeItem.value
		})
	}
]);
