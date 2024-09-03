import { ActionTargetItem, fDate, fNumber, TableColumn } from "quasar-ui-danx";
import { actionControls } from "./actions";
import { controls } from "./controls";

const onEdit = (promptDirective: ActionTargetItem) => controls.activatePanel(promptDirective, "edit");

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
