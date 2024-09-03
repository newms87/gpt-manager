import { ActionTargetItem, fDate, fNumber, TableColumn } from "quasar-ui-danx";
import { controls } from "./controls";

const onEdit = (promptSchema: ActionTargetItem) => controls.activatePanel(promptSchema, "edit");

export const columns: TableColumn[] = [
	{
		name: "id",
		label: "ID",
		align: "left",
		sortable: true,
		shrink: true,
		onClick: onEdit
	},
	{
		name: "name",
		label: "Name",
		align: "left",
		sortable: true,
		onClick: onEdit
	},
	{
		name: "description",
		label: "Description",
		sortable: true,
		align: "left"
	},
	{
		name: "schema_format",
		label: "Schema Format",
		sortable: true,
		align: "left"
	},
	{
		name: "agents_count",
		label: "Agents",
		format: fNumber,
		sortable: true
	},
	{
		name: "workflow_jobs_count",
		label: "Workflow Jobs",
		format: fNumber,
		sortable: true
	},
	{
		name: "created_at",
		label: "Created Date",
		sortable: true,
		format: fDate
	},
	{
		name: "updated_at",
		label: "Updated Date",
		sortable: true,
		format: fDate
	}
];
