import { PromptDirectiveController } from "@/components/Modules/Prompts/Directives/promptDirectiveControls";
import { CreateNewWithNameDialog } from "@/components/Shared";
import { PromptDirectiveRoutes } from "@/routes/promptRoutes";
import { FaSolidCopy as CopyIcon, FaSolidPencil as EditIcon, FaSolidTrash as DeleteIcon } from "danx-icon";
import { ActionOptions, ActionTarget, ConfirmActionDialog, useActions } from "quasar-ui-danx";
import { h } from "vue";


// This is the default action options for all items
const forAllItems: Partial<ActionOptions> = {
	onAction: PromptDirectiveRoutes.applyAction
};

const items: ActionOptions[] = [
	{
		name: "create",
		label: "Create Directive",
		vnode: () => h(CreateNewWithNameDialog, { title: "Create Directive" }),
		onFinish: (result) => {
			PromptDirectiveController.activatePanel(result.item, "edit");
			PromptDirectiveController.loadListAndSummary();
		}
	},
	{
		name: "update",
		optimistic: true
	},
	{
		name: "update-debounced",
		alias: "update",
		debounce: 1000
	},
	{
		name: "copy",
		label: "Copy",
		icon: CopyIcon,
		menu: true,
		onSuccess: PromptDirectiveController.loadListAndSummary
	},
	{
		label: "Edit",
		name: "edit",
		icon: EditIcon,
		menu: true,
		onAction: (action, target) => PromptDirectiveController.activatePanel(target, "edit")
	},
	{
		name: "delete",
		label: "Delete",
		class: "text-red-500",
		iconClass: "text-red-500",
		icon: DeleteIcon,
		menu: true,
		batch: true,
		onFinish: PromptDirectiveController.loadListAndSummary,
		vnode: (target: ActionTarget) => h(ConfirmActionDialog, {
			action: "Delete",
			label: "Directive",
			target,
			confirmClass: "bg-red-900"
		})
	}
];

export const { getAction, getActions, extendAction } = useActions(items, forAllItems);
