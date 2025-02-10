import { PromptDirective } from "@/types";
import { fDate, fNumber, TableColumn } from "quasar-ui-danx";
import { controls } from "./controls";

const onEdit = (promptDirective: PromptDirective) => controls.activatePanel(promptDirective, "edit");

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
		name: "agents_count",
		label: "Agents",
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
