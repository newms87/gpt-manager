export interface WorkflowStatus {
	value: string;
	classPrimary: string;
	classAlt: string;
}

export const WORKFLOW_STATUS = {
	resolve(value): WorkflowStatus {
		return [WORKFLOW_STATUS.PENDING, WORKFLOW_STATUS.RUNNING, WORKFLOW_STATUS.COMPLETED, WORKFLOW_STATUS.FAILED, WORKFLOW_STATUS.TIMED_OUT].find(s => s.value === value);
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
	COMPLETED: {
		value: "Completed",
		classPrimary: "bg-green-900 text-green-200",
		classAlt: "bg-green-200 text-green-900"
	},
	FAILED: {
		value: "Failed",
		classPrimary: "bg-red-800 text-red-200",
		classAlt: "bg-red-200 text-red-800"
	},
	TIMED_OUT: {
		value: "Timed Out",
		classPrimary: "bg-gray-800 text-gray-200",
		classAlt: "bg-gray-200 text-gray-800"
	}
};
