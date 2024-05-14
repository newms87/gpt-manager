import { AuditRequest } from "@/components/Modules/Audits/audit-requests";
import { AuditRequestController } from "@/components/Modules/Audits/auditRequestControls";
import {
	AuditRequestAuditsPanel,
	AuditRequestErrorsPanel,
	AuditRequestJobsPanel,
	AuditRequestRequestPanel,
	AuditRequestResponsePanel
} from "@/components/Modules/Audits/Panels";
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
		vnode: () => h(AuditRequestAuditsPanel, {
			auditRequest: activeItem.value
		})
	},
	{
		name: "api-logs",
		label: "API Logs",
		class: "w-[80em]",
		vnode: () => h(AuditRequestAuditsPanel, {
			auditRequest: activeItem.value
		})
	},
	{
		name: "ran-jobs",
		label: "Ran Jobs",
		class: "w-[80em]",
		vnode: () => h(AuditRequestJobsPanel, {
			auditRequest: activeItem.value,
			jobs: activeItem.value.ran_jobs || []
		})
	},
	{
		name: "dispatched-jobs",
		label: "Dispatched Jobs",
		class: "w-[80em]",
		vnode: () => h(AuditRequestJobsPanel, {
			auditRequest: activeItem.value,
			jobs: activeItem.value.dispatched_jobs || []
		})
	},
	{
		name: "errors",
		label: "Errors",
		class: "w-[80em]",
		vnode: () => h(AuditRequestErrorsPanel, {
			auditRequest: activeItem.value
		})
	}
]);
