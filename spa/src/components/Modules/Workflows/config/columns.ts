import { fDate, fNumber, TableColumn } from "quasar-ui-danx";
import { controls } from "./controls";

export const columns: TableColumn[] = [
	{
		name: "id",
		label: "ID",
		align: "left",
		sortable: true,
		shrink: true,
		onClick: (workflow) => controls.activatePanel(workflow, "edit")
	},
	{
		name: "name",
		label: "Name",
		align: "left",
		sortable: true,
		onClick: (workflow) => controls.activatePanel(workflow, "edit")
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
		onClick: (workflow) => controls.activatePanel(workflow, "jobs")
	},
	{
		name: "runs_count",
		label: "Workflow Runs",
		align: "left",
		format: fNumber,
		sortable: true,
		onClick: (workflow) => controls.activatePanel(workflow, "runs")
	},
	{
		name: "created_at",
		label: "Created Date",
		sortable: true,
		align: "left",
		format: fDate
	}
];
