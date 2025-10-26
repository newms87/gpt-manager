<script setup>
import PusherDebugPanel from "@/components/Developer/PusherDebugPanel.vue";
import { usePusherDebug } from "@/composables/usePusherDebug";
import { usePusher } from "@/helpers/pusher";
import { useQuasar } from "quasar";
import { FlashMessages } from "quasar-ui-danx";
import { onMounted } from "vue";
import { RouterView } from "vue-router";
import { UniversalAssistant } from "./components/Modules/Assistant";

const { showDebugPanel } = usePusherDebug();

onMounted(() => {
	FlashMessages.notify = useQuasar().notify;
	usePusher();
});
</script>

<template>
	<RouterView />
	<UniversalAssistant />
	<PusherDebugPanel v-if="showDebugPanel" @close="showDebugPanel = false" />
</template>
