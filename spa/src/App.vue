<script setup>
import { RouterView } from "vue-router";
import { OAuthCallbackMessage } from "./components";
import { UniversalAssistant } from "./components/Modules/Assistant";
</script>

<template>
	<OAuthCallbackMessage />
	<RouterView />
	<UniversalAssistant />
</template>
