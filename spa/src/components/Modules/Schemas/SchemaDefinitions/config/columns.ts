import { SchemaDefinition } from "@/types";
import { fDate, TableColumn } from "quasar-ui-danx";
import { controls } from "./controls";

const onEdit = (schemaDefinition: SchemaDefinition) => controls.activatePanel(schemaDefinition, "edit");

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
