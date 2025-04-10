import { fDate, fNumber, TableColumn } from "quasar-ui-danx";
import { controls } from "./controls";

export const columns: TableColumn[] = [
	{
		name: "id",
		label: "ID",
		align: "left",
		sortable: true,
		shrink: true,
		onClick: (agent) => controls.activatePanel(agent, "edit")
	},
	{
		name: "name",
		label: "Name",
		align: "left",
		sortable: true,
		onClick: (agent) => controls.activatePanel(agent, "edit")
	},
	{
		name: "description",
		label: "Description",
		sortable: true,
		align: "left"
	},
	{
		name: "task_runner_name",
		label: "Task Runner",
		sortable: true,
		align: "left"
	},
	{
		name: "task_run_count",
		label: "Task Runs",
		format: fNumber,
		sortable: true,
		onClick: (agent) => controls.activatePanel(agent, "task_runs")
	},
	{
		name: "task_agent_count",
		label: "Agents",
		format: fNumber,
		sortable: true
	},
	{
		name: "created_at",
		label: "Created Date",
		sortable: true,
		format: fDate
	}
];
