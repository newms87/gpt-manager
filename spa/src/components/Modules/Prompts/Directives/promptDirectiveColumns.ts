import { getActions } from "@/components/Modules/Prompts/Directives/promptDirectiveActions";
import { PromptDirectiveController } from "@/components/Modules/Prompts/Directives/promptDirectiveControls";
import { ActionTargetItem, fDate, fNumber, TableColumn } from "quasar-ui-danx";

const onEdit = (promptDirective: ActionTargetItem) => PromptDirectiveController.activatePanel(promptDirective, "edit");

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
