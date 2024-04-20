<template>
    <div id="primary-layout" class="min-w-xs">
        <ThePageHeader />
        <CollapsableSidebar min-width="0" name="primary-nav">
            <NavigationMenu :items="adminMenu" />
        </CollapsableSidebar>
        <transition
            mode="out-in"
            :duration="300"
        >
            <main :key="$route.name">
                <slot />
            </main>
        </transition>
    </div>
</template>
<script setup lang="ts">
import ThePageHeader from "@/components/ThePageHeader";
import { useQuasar } from "quasar";
import { CollapsableSidebar, FlashMessages } from "quasar-ui-danx";
import { onMounted } from "vue";

onMounted(() => {
    FlashMessages.notify = useQuasar().notify;
});
</script>
