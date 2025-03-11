import { dxAgent } from "@/components/Modules/Agents";
import { ref, shallowRef } from "vue";

const isLoadingAgents = ref(false);
const availableAgents = shallowRef([]);

async function loadAgents() {
	if (isLoadingAgents.value) return;
	isLoadingAgents.value = true;
	availableAgents.value = (await dxAgent.routes.list()).data;
	isLoadingAgents.value = false;
}

export {
	isLoadingAgents,
	loadAgents,
	availableAgents
};
