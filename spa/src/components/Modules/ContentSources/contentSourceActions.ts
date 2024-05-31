import { ContentSourceController } from "@/components/Modules/ContentSources/contentSourceControls";
import { CreateNewWithNameDialog } from "@/components/Shared";
import { ContentSourceRoutes } from "@/routes/contentSourceRoutes";
import { ConfirmActionDialog, useActions } from "quasar-ui-danx";
import { ActionOptions } from "quasar-ui-danx/types";
import { h } from "vue";

// This is the default action options for all items
const forAllItems: ActionOptions = {
	onAction: ContentSourceRoutes.applyAction,
	onBatchAction: ContentSourceRoutes.batchAction,
	onBatchSuccess: ContentSourceController.clearSelectedRows
};

const items: ActionOptions[] = [
	{
		name: "create",
		label: "Create Content Source",
		vnode: () => h(CreateNewWithNameDialog, { title: "Create Content Source" }),
		onFinish: (result) => {
			ContentSourceController.activatePanel(result.item, "edit");
			ContentSourceController.loadListAndSummary();
		}
	},
	{
		name: "update"
	},
	{
		name: "update-debounced",
		alias: "update",
		debounce: 1000
	},
	{
		label: "Edit",
		name: "edit",
		menu: true,
		onAction: (action, target) => ContentSourceController.activatePanel(target, "edit")
	},
	{
		name: "delete",
		label: "Delete",
		class: "text-red-500",
		menu: true,
		batch: true,
		onFinish: ContentSourceController.loadListAndSummary,
		vnode: target => h(ConfirmActionDialog, {
			action: "Delete",
			label: "ContentSources",
			target,
			confirmClass: "bg-red-900"
		})
	}
];

export const { getAction, getActions } = useActions(items, forAllItems);
