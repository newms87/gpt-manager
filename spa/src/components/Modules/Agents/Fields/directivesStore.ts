import { dxPromptDirective } from "@/components/Modules/Prompts";
import { ref, shallowRef } from "vue";

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
	promptDirectives.value = (await dxPromptDirective.routes.list({ sort: [{ column: "name" }] })).data;
	isRefreshingPromptDirectives.value = false;

}

export {
	promptDirectives,
	isRefreshingPromptDirectives,
	hasLoadedPromptDirectives,
	refreshPromptDirectives,
	loadPromptDirectives
};
