import { dxAgent } from "@/components/Modules/Agents";
import { Agent } from "@/types";
import { ref, shallowRef } from "vue";

const isLoadingAgents = ref(false);
const agentToLoadDetails = ref(null);
const availableAgents = shallowRef([]);

async function loadAgents() {
	if (isLoadingAgents.value) return;
	isLoadingAgents.value = true;
	availableAgents.value = (await dxAgent.routes.list({ sort: [{ column: "name" }] })).data;
	isLoadingAgents.value = false;
}

async function loadAgentDetails(agent: Agent) {
	agentToLoadDetails.value = agent;
	await dxAgent.routes.details(agent);

	// Only indicate the loading has stopped if the most recent agentToLoadDetails matches the agent of this request
	// NOTE: This may be called multiple times subsequently before the request finished, as the user has already moved onto another agent.
	if (agentToLoadDetails.value?.id === agent.id) {
		agentToLoadDetails.value = null;
	}
}

export {
	isLoadingAgents,
	agentToLoadDetails,
	loadAgents,
	loadAgentDetails,
	availableAgents
};
