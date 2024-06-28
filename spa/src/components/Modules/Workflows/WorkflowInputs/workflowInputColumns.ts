import { getActions } from "@/components/Modules/Workflows/WorkflowInputs/workflowInputActions";
import { WorkflowInputController } from "@/components/Modules/Workflows/WorkflowInputs/workflowInputControls";
import { fDate, fNumber } from "quasar-ui-danx";
import { TableColumn } from "quasar-ui-danx/types";

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
		required: true,
		shrink: true,
		onClick: (workflowInput) => WorkflowInputController.activatePanel(workflowInput, "edit")
	},
	{
		name: "name",
		label: "Name",
		align: "left",
		sortable: true,
		onClick: (workflowInput) => WorkflowInputController.activatePanel(workflowInput, "edit")
	},
	{
		name: "description",
		label: "Description",
		sortable: true,
		align: "left"
	},
	{
		name: "tags",
		label: "Tags",
		align: "left",
		format: v => v.join(", ")
	},
	{
		name: "workflow_runs_count",
		label: "WorkflowInput Runs",
		align: "left",
		format: fNumber,
		sortable: true,
		onClick: (workflowInput) => WorkflowInputController.activatePanel(workflowInput, "runs")
	},
	{
		name: "created_at",
		label: "Created Date",
		sortable: true,
		align: "left",
		format: fDate
	}
];
