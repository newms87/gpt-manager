import { fDate, TableColumn } from "quasar-ui-danx";
import { controls } from "./controls";

export const columns: TableColumn[] = [
	{
		name: "id",
		label: "ID",
		align: "left",
		sortable: true,
		required: true,
		shrink: true,
		onClick: (workflowInput) => controls.activatePanel(workflowInput, "edit")
	},
	{
		name: "name",
		label: "Name",
		align: "left",
		sortable: true,
		onClick: (workflowInput) => controls.activatePanel(workflowInput, "edit")
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
		name: "created_at",
		label: "Created Date",
		sortable: true,
		align: "left",
		format: fDate
	}
];
