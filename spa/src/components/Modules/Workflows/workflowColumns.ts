import { getActions } from "@/components/Modules/Workflows/workflowActions";
import { WorkflowController } from "@/components/Modules/Workflows/workflowControls";
import { fDate, fNumber } from "quasar-ui-danx";
import { TableColumn } from "quasar-ui-danx";

export const columns: TableColumn[] = [
	{
		name: "menu",
		label: "",
		required: true,
		hideContent: true,
		shrink: true,
		actionMenu: getActions({ menu: true })
	},
	{
		name: "id",
		label: "ID",
		align: "left",
		sortable: true,
		shrink: true,
		onClick: (workflow) => WorkflowController.activatePanel(workflow, "edit")
	},
	{
		name: "name",
		label: "Name",
		align: "left",
		sortable: true,
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
