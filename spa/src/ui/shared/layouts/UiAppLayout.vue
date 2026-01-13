<template>
    <div class="ui-app min-h-screen h-screen bg-gradient-to-br from-slate-50 to-blue-50 flex flex-col overflow-hidden">
        <UiHeader />

        <div class="flex flex-grow overflow-hidden">
            <UiSidebar v-if="config.showSidebar" />

            <main class="flex-1 overflow-hidden">
                <router-view />
            </main>
        </div>

        <ActionVnode />
    </div>
</template>

<script setup lang="ts">
import { onMounted, onUnmounted } from "vue";
import { ActionVnode } from "quasar-ui-danx";
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
    document.body.classList.add("ui-mode");
});

onUnmounted(() => {
    // Remove UI mode class from body
    document.body.classList.remove("ui-mode");
});
</script>

<style lang="scss">
// Import UI styles
@import "../styles/index.scss";
</style>
