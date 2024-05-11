import { AgentRoutes } from "@/routes/agentRoutes";
import { useListControls } from "quasar-ui-danx";
import { ActionController } from "quasar-ui-danx/types";

export const AgentController: ActionController = useListControls("agents", { label: "Agents", routes: AgentRoutes });
