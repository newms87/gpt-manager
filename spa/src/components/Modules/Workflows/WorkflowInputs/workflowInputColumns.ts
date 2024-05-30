import { getActions } from "@/components/Modules/Workflows/WorkflowInputs/workflowInputActions";
import { WorkflowInputController } from "@/components/Modules/Workflows/WorkflowInputs/workflowInputControls";
import { fDate, fNumber } from "quasar-ui-danx";
import { TableColumn } from "quasar-ui-danx/types";
import { h } from "vue";

export const columns: TableColumn[] = [
	{
		name: "name",
		label: "Name",
		align: "left",
		sortable: true,
		required: true,
		actionMenu: getActions({ menu: true }),
		onClick: (workflowInput) => WorkflowInputController.activatePanel(workflowInput, "edit")
	},
	{
		name: "description",
		label: "Description",
		sortable: true,
		align: "left"
	},
	{
		name: "data",
		label: "Input",
		align: "left",
		vnode: () => h("div", "Render the data for a column")
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
