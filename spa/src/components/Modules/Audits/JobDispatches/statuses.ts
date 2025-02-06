import { ResourceStatus } from "@/types";

export const JOB_DISPATCH_STATUS = {
	resolve(value): ResourceStatus {
		return [
			JOB_DISPATCH_STATUS.ABORTED,
			JOB_DISPATCH_STATUS.COMPLETE,
			JOB_DISPATCH_STATUS.EXCEPTION,
			JOB_DISPATCH_STATUS.FAILED,
			JOB_DISPATCH_STATUS.PENDING,
			JOB_DISPATCH_STATUS.RUNNING,
			JOB_DISPATCH_STATUS.TIMEOUT
		].find(s => s.value === value) || JOB_DISPATCH_STATUS.PENDING;
	},

	ABORTED: {
		value: "Stopped",
		classPrimary: "bg-slate-700 text-slate-300",
		classAlt: "bg-slate-300 text-slate-700"
	},
	COMPLETE: {
		value: "Complete",
		classPrimary: "bg-green-900 text-green-200",
		classAlt: "bg-green-200 text-green-900"
	},
	EXCEPTION: {
		value: "Failed",
		classPrimary: "bg-red-800 text-red-200",
		classAlt: "bg-red-200 text-red-800"
	},
	FAILED: {
		value: "Failed",
		classPrimary: "bg-red-800 text-red-200",
		classAlt: "bg-red-200 text-red-800"
	},
	PENDING: {
		value: "Pending",
		classPrimary: "bg-slate-700 text-slate-300",
		classAlt: "bg-slate-300 text-slate-700"
	},
	RUNNING: {
		value: "Running",
		classPrimary: "bg-sky-800 text-sky-200",
		classAlt: "bg-sky-200 text-sky-800"
	},
	TIMEOUT: {
		value: "Timeout",
		classPrimary: "bg-gray-800 text-gray-200",
		classAlt: "bg-gray-200 text-gray-800"
	}
};
