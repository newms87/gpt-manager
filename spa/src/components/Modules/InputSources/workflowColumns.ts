import { getActions } from "@/components/Modules/Workflows/workflowActions";
import { WorkflowController } from "@/components/Modules/Workflows/workflowControls";
import { fDate, fNumber } from "quasar-ui-danx";
import { TableColumn } from "quasar-ui-danx/types";

export const columns: TableColumn[] = [
	{
		name: "name",
		label: "Name",
		align: "left",
		sortable: true,
		required: true,
		actionMenu: getActions({ menu: true }),
		onClick: (workflow) => WorkflowController.activatePanel(workflow, "edit")
	},
	{
		name: "description",
		label: "Description",
		sortable: true,
		align: "left"
	},
	{
		name: "jobs_count",
		label: "Workflow Jobs",
		align: "left",
		format: fNumber,
		sortable: true,
		onClick: (workflow) => WorkflowController.activatePanel(workflow, "jobs")
	},
	{
		name: "runs_count",
		label: "Workflow Runs",
		align: "left",
		format: fNumber,
		sortable: true,
		onClick: (workflow) => WorkflowController.activatePanel(workflow, "runs")
	},
	{
		name: "created_at",
		label: "Created Date",
		sortable: true,
		align: "left",
		format: fDate
	}
];
