import { FaSolidCopy as CopyIcon } from "danx-icon";
import { ActionOptions, useActions, withDefaultActions } from "quasar-ui-danx";
import { dxPromptSchema } from "./controls";
import { PromptSchemaRoutes } from "./routes";


// This is the default action options for all items
const forAllItems: Partial<ActionOptions> = {
	onAction: PromptSchemaRoutes.applyAction,
	onBatchAction: PromptSchemaRoutes.batchAction
};

const items: ActionOptions[] = [
	...withDefaultActions(dxPromptSchema),
	{
		name: "copy",
		label: "Copy",
		icon: CopyIcon,
		menu: true,
		onSuccess: dxPromptSchema.loadListAndSummary
	}
];

export const { getAction, getActions, extendAction } = useActions(items, forAllItems);
