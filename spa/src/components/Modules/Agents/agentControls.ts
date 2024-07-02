import { AgentRoutes } from "@/routes/agentRoutes";
import { ActionController, useListControls } from "quasar-ui-danx";

export const AgentController: ActionController = useListControls("agents", { label: "Agents", routes: AgentRoutes });
