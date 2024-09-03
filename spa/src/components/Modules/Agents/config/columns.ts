import { fDate, fNumber, TableColumn } from "quasar-ui-danx";
import { actionControls } from "./actions";
import { controls } from "./controls";

export const columns: TableColumn[] = [
	{
		name: "menu",
		label: "",
		required: true,
		hideContent: true,
		shrink: true,
		actionMenu: actionControls.getActions({ menu: true })
	},
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
		name: "api",
		label: "API",
		sortable: true,
		align: "left"
	},
	{
		name: "model",
		label: "Model",
		sortable: true,
		align: "left"
	},
	{
		name: "temperature",
		label: "Temperature",
		sortable: true
	},
	{
		name: "threads_count",
		label: "Threads",
		format: fNumber,
		sortable: true,
		onClick: (agent) => controls.activatePanel(agent, "threads")
	},
	{
		name: "assignments_count",
		label: "Assignments",
		format: fNumber,
		sortable: true,
		onClick: (agent) => controls.activatePanel(agent, "assignments")
	},
	{
		name: "created_at",
		label: "Created Date",
		sortable: true,
		format: fDate
	}
];
