import { PromptSchemaFragment } from "@/types";
import { DanxController } from "quasar-ui-danx";
import { actionControls } from "./actions";
import { controls } from "./controls";
import { routes } from "./routes";

export const dxPromptSchema = {
	...controls,
	...actionControls,
	routes
} as DanxController<PromptSchemaFragment>;
