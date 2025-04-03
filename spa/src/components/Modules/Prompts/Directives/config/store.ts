import { ActionStore, ListControlsRoutes } from "quasar-ui-danx";
import { ref, shallowRef } from "vue";
import { routes } from "./routes";

function useActionStore(routes: ListControlsRoutes): ActionStore {
	const listItems = shallowRef([]);
	const isRefreshing = ref(false);
	const hasLoadedItems = ref(false);

	async function loadItems() {
		if (hasLoadedItems.value) return;
		await refreshPromptDirectives();
		hasLoadedItems.value = true;
		return listItems.value;
	}

	async function refreshItems() {
		if (isRefreshing.value) return;
		isRefreshing.value = true;
		listItems.value = (await routes.list({ sort: [{ column: "name" }] })).data;
		isRefreshing.value = false;
		return listItems.value;
	}

	return {
		listItems,
		isRefreshing,
		hasLoadedItems,
		loadItems,
		refreshItems
	};
}

export const store = useActionStore(routes);

const promptDirectives = shallowRef([]);
const isRefreshingPromptDirectives = ref(false);
const hasLoadedPromptDirectives = ref(false);

async function loadPromptDirectives() {
	if (hasLoadedPromptDirectives.value) return;
	await refreshPromptDirectives();
	hasLoadedPromptDirectives.value = true;
}

async function refreshPromptDirectives() {
	if (isRefreshingPromptDirectives.value) return;
	isRefreshingPromptDirectives.value = true;
	promptDirectives.value = (await routes.list({ sort: [{ column: "name" }] })).data;
	isRefreshingPromptDirectives.value = false;

}

export {
	promptDirectives,
	isRefreshingPromptDirectives,
	hasLoadedPromptDirectives,
	refreshPromptDirectives,
	loadPromptDirectives
};
