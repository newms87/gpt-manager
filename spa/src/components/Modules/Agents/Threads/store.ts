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
async function refreshAgentThread(agentThread: AgentThread) {
	await dxAgentThread.routes.details(agentThread, AgentThreadFields);
}

export {
	AgentThreadFields,
	refreshAgentThread
};
