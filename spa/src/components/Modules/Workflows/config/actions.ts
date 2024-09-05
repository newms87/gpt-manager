import { ImportWorkflowDialog } from "@/components/Modules/Workflows/Shared";
import { Workflow } from "@/types";
import { FaSolidDatabase as ExportJsonIcon, FaSolidPlay as RunIcon } from "danx-icon";
import {
	ActionOptions,
	ConfirmActionDialog,
	CreateNewWithNameDialog,
	useActions,
	withDefaultActions
} from "quasar-ui-danx";
import { h } from "vue";
import { controls } from "./controls";
import { routes, WorkflowAssignmentRoutes, WorkflowJobRoutes } from "./routes";

export const actions: ActionOptions[] = [
	...withDefaultActions("Workflow", controls),
	{
		name: "run-workflow",
		icon: RunIcon
	},
	{
		name: "export-json",
		label: "Export as JSON",
		icon: ExportJsonIcon,
		onAction: async (action, target: Workflow) => await routes.exportAsJson(target)
	},
	{
		name: "import-json",
		label: "Import from JSON",
		onAction: routes.applyAction,
		onFinish: controls.loadListAndSummary,
		vnode: () => h(ImportWorkflowDialog)
	},
	{
		name: "create-job",
		vnode: () => h(CreateNewWithNameDialog, { title: "Create Workflow Job", confirmText: "Create Job" })
	},
	{
		name: "update-job",
		alias: "update",
		optimistic: true,
		onAction: WorkflowJobRoutes.applyAction
	},
	{
		name: "update-job-debounced",
		alias: "update",
		debounce: 500,
		onAction: WorkflowJobRoutes.applyAction
	},
	{
		name: "delete-job",
		alias: "delete",
		vnode: (target) => h(ConfirmActionDialog, { action: "Delete Job", target, confirmClass: "bg-red-700" }),
		onAction: async (...params) => {
			await WorkflowJobRoutes.applyAction(...params);
			await controls.getActiveItemDetails();
		}
	},
	{
		name: "set-dependencies",
		onAction: WorkflowJobRoutes.applyAction,
		onFinish: controls.getActiveItemDetails
	},
	{
		name: "assign-agent",
		onAction: WorkflowJobRoutes.applyAction
	},
	{
		name: "unassign-agent",
		alias: "delete",
		onAction: WorkflowAssignmentRoutes.applyAction,
		onFinish: controls.getActiveItemDetails
	}
];

export const actionControls = useActions(actions, { routes, controls });

export const menuActions = actionControls.getActions(["run-workflow", "export-json", "edit", "copy", "delete"]);
export const batchActions = actionControls.getActions(["delete"]);
