import { FaSolidCopy as CopyIcon } from "danx-icon";
import { ActionOptions, useActions, withDefaultActions } from "quasar-ui-danx";
import { controls } from "./controls";
import { routes } from "./routes";

export const actions: ActionOptions[] = [
	...withDefaultActions(controls),
	{
		name: "copy",
		label: "Copy",
		icon: CopyIcon,
		menu: true,
		onSuccess: controls.loadListAndSummary
	}
];

export const actionControls = useActions(actions, { routes });
