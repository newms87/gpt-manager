import { dxPromptSchema } from "@/components/Modules/Prompts/Schemas/promptSchemaControls";
import { CreateNewWithNameDialog } from "@/components/Shared";
import { PromptSchemaRoutes } from "@/routes/promptRoutes";
import { FaSolidCopy as CopyIcon, FaSolidPencil as EditIcon, FaSolidTrash as DeleteIcon } from "danx-icon";
import { ActionOptions, ActionTarget, ConfirmActionDialog, useActions } from "quasar-ui-danx";
import { h } from "vue";


// This is the default action options for all items
const forAllItems: Partial<ActionOptions> = {
	onAction: PromptSchemaRoutes.applyAction,
	onBatchAction: PromptSchemaRoutes.batchAction
};

const items: ActionOptions[] = [
	{
		name: "create",
		label: "Create Prompt Schema",
		vnode: () => h(CreateNewWithNameDialog, { title: "Create Prompt Schema" }),
		onFinish: (result) => {
			dxPromptSchema.activatePanel(result.item, "edit");
			dxPromptSchema.loadListAndSummary();
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
		onSuccess: dxPromptSchema.loadListAndSummary
	},
	{
		label: "Edit",
		name: "edit",
		icon: EditIcon,
		menu: true,
		onAction: (action, target) => dxPromptSchema.activatePanel(target, "edit")
	},
	{
		name: "delete",
		label: "Delete",
		class: "text-red-500",
		iconClass: "text-red-500",
		icon: DeleteIcon,
		menu: true,
		batch: true,
		onFinish: dxPromptSchema.loadListAndSummary,
		vnode: (target: ActionTarget) => h(ConfirmActionDialog, {
			action: "Delete",
			label: "PromptSchemas",
			target,
			confirmClass: "bg-red-900"
		})
	}
];

export const { getAction, getActions, extendAction } = useActions(items, forAllItems);
