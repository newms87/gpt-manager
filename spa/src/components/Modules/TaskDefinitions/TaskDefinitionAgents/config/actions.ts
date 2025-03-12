import { ActionOptions, useActions, withDefaultActions } from "quasar-ui-danx";
import { controls } from "./controls";
import { routes } from "./routes";

export const actions: ActionOptions[] = withDefaultActions("Task Definition Agent", controls);
export const actionControls = useActions(actions, { routes });
