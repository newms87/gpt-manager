import { InputSourceController } from "@/components/Modules/InputSources/inputSourceControls";
import { CreateNewWithNameDialog } from "@/components/Shared";
import { InputSourceRoutes } from "@/routes/inputSourceRoutes";
import { ConfirmActionDialog, useActions } from "quasar-ui-danx";
import { ActionOptions } from "quasar-ui-danx/types";
import { h } from "vue";

// This is the default action options for all items
const forAllItems: ActionOptions = {
	onAction: InputSourceRoutes.applyAction,
	onBatchAction: InputSourceRoutes.batchAction,
	onBatchSuccess: InputSourceController.clearSelectedRows
};

const items: ActionOptions[] = [
	{
		name: "create",
		label: "Create InputSource",
		vnode: () => h(CreateNewWithNameDialog, { title: "Create InputSource" }),
		onFinish: (result) => {
			InputSourceController.activatePanel(result.item, "edit");
			InputSourceController.refreshAll();
		}
	},
	{
		name: "update",
		onFinish: InputSourceController.loadList
	},
	{
		name: "update-debounced",
		alias: "update",
		debounce: 1000,
		onFinish: InputSourceController.loadList
	},
	{
		label: "Edit",
		name: "edit",
		menu: true,
		onAction: (action, target) => InputSourceController.activatePanel(target, "edit")
	},
	{
		name: "delete",
		label: "Delete",
		class: "text-red-500",
		menu: true,
		batch: true,
		onFinish: InputSourceController.refreshAll,
		vnode: target => h(ConfirmActionDialog, {
			action: "Delete",
			label: "InputSources",
			target,
			confirmClass: "bg-red-900"
		})
	}
];

export const { getAction, getActions } = useActions(items, forAllItems);
