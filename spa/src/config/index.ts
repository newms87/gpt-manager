import { computed } from "vue";
import { default as OnDemands } from "./ondemands";
import { default as TortGuard } from "./tortguard";

const currentDomain = window.location.hostname;

export const siteSettings = computed(() => {
	for (const config of [OnDemands, TortGuard]) {
		if (config.domains.includes(currentDomain)) {
			return config;
		}
	}

	return TortGuard;
});
