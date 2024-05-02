import { AgentRoutes } from "@/routes/agentRoutes";
import { useListControls } from "quasar-ui-danx";

export const AgentController = useListControls("agents", { label: "Agents", routes: AgentRoutes });
