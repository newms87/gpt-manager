import { AgentRoutes } from "@/components/Modules/Agents/config/routes";
import { ActionController, useListControls } from "quasar-ui-danx";

export const dxAgent: ActionController = useListControls("agents", { label: "Agents", routes: AgentRoutes });
