import { ActionOptions, useActions, withDefaultActions } from "quasar-ui-danx";
import { controls } from "./controls";
import { routes } from "./routes";

const items: ActionOptions[] = [
	...withDefaultActions(controls)
];

export const actionControls = useActions(items, { routes });
