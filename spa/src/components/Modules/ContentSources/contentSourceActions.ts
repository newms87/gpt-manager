import { dxContentSource } from "@/components/Modules/ContentSources/contentSourceControls";
import { ContentSourceRoutes } from "@/routes/contentSourceRoutes";
import { ActionOptions, useActions, withDefaultActions } from "quasar-ui-danx";

// This is the default action options for all items
const forAllItems: Partial<ActionOptions> = {
	onAction: ContentSourceRoutes.applyAction,
	onBatchAction: ContentSourceRoutes.batchAction,
	onBatchSuccess: dxContentSource.clearSelectedRows
};

const items: ActionOptions[] = [
	...withDefaultActions("Content Sources", dxContentSource),
	{
		name: "fetch"
	}
];

export const { getAction, getActions } = useActions(items, forAllItems);
