import { TemplateDefinition } from "../types";
import { DanxController } from "quasar-ui-danx";
import { actionControls } from "./actions";
import { controls } from "./controls";
import { routes } from "./routes";

export const dxTemplateDefinition = {
	...controls,
	...actionControls,
	routes
} as DanxController<TemplateDefinition>;

/**
 * @deprecated Use dxTemplateDefinition instead
 */
export const dxDemandTemplate = dxTemplateDefinition;