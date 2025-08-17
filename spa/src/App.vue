<script setup>
import { usePusher } from "@/helpers/pusher";
import { useQuasar } from "quasar";
import { FlashMessages } from "quasar-ui-danx";
import { onMounted } from "vue";
import { RouterView } from "vue-router";
import { OAuthCallbackMessage } from "./components";
import { UniversalAssistant } from "./components/Modules/Assistant";

onMounted(() => {
	FlashMessages.notify = useQuasar().notify;
	usePusher();
});
</script>

<template>
	<OAuthCallbackMessage />
	<RouterView />
	<UniversalAssistant />
</template>
