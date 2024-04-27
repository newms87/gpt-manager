import { Agents } from "@/routes/agents";
import { useListControls } from "quasar-ui-danx";

export const AgentController = useListControls("agents", { label: "Agents", routes: Agents });
