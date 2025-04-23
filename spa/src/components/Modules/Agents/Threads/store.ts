import { dxAgentThread } from "@/components/Modules/Agents/Threads/config";
import { AgentThread } from "@/types";

const AgentThreadFields = {
	messages: {
		files: {
			thumb: true,
			transcodes: true
		}
	}
};
async function refreshAgentThread(agentThread: AgentThread): Promise<AgentThread> {
	return await dxAgentThread.routes.details(agentThread, AgentThreadFields) as AgentThread;
}

export {
	AgentThreadFields,
	refreshAgentThread
};
