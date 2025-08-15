import { ActionOptions, useActions, withDefaultActions } from "quasar-ui-danx";
import { controls } from "./controls";
import { routes } from "./routes";

export const actions: ActionOptions[] = [
	...withDefaultActions("Demand Template", controls)
];

export const actionControls = useActions(actions, { routes, controls });
