<template>
	<div class="ui-app min-h-screen bg-gradient-to-br from-slate-50 to-blue-50 flex flex-col">
		<UiHeader />

		<div class="flex flex-1">
			<UiSidebar v-if="config.showSidebar" />

			<main class="flex-1 overflow-auto">
				<div class="p-6">
					<router-view />
				</div>
			</main>
		</div>
	</div>
</template>

<script setup lang="ts">
import { onMounted, onUnmounted } from "vue";
import { uiNavigation } from "../../../navigation/uiNavigation";
import { useUiLayout, useUiNavigation, useUiTheme } from "../composables";
import UiHeader from "./UiHeader.vue";
import UiSidebar from "./UiSidebar.vue";

const { config } = useUiLayout();
const { applyTheme } = useUiTheme();
const { setNavigation } = useUiNavigation();

onMounted(() => {
	// Apply theme
	applyTheme();

	// Set navigation items
	setNavigation(uiNavigation);
	
	// Add UI mode class to body for scoped styling
	document.body.classList.add('ui-mode');
});

onUnmounted(() => {
	// Remove UI mode class from body
	document.body.classList.remove('ui-mode');
});
</script>

<style lang="scss">
// Import UI styles
@import "../styles/index.scss";
</style>
