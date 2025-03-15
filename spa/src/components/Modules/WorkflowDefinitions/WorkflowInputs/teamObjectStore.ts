import { dxTeamObject } from "@/components/Modules/TeamObjects";
import { ref, shallowRef } from "vue";

const availableTeamObjectsByType = shallowRef([]);
const hasLoadedAvailableTeamObjects = ref({});
const isLoadingAvailableTeamObjects = ref({});

async function loadAvailableTeamObjectsByType(type: string) {
	if (!type || hasLoadedAvailableTeamObjects.value[type]) return;

	await refreshAvailableTeamObjects(type);
	hasLoadedAvailableTeamObjects.value[type] = true;
}

async function refreshAvailableTeamObjects(type: string) {
	if (!type || isLoadingAvailableTeamObjects.value[type]) return;

	isLoadingAvailableTeamObjects.value[type] = true;
	const teamObjects = (await dxTeamObject.routes.list({
		filter: { type },
		sort: [{ column: "name" }]
	})).data;

	availableTeamObjectsByType.value = { ...availableTeamObjectsByType.value, [type]: teamObjects };
	isLoadingAvailableTeamObjects.value[type] = false;
}

export {
	isLoadingAvailableTeamObjects,
	availableTeamObjectsByType,
	refreshAvailableTeamObjects,
	loadAvailableTeamObjectsByType
};
