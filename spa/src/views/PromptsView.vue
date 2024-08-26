<template>
	<div class="h-full">
		<QTabs
			:model-value="currentPage as string"
			align="left"
			class="ml-4"
			@update:model-value="$router.push({name: $event})"
		>
			<QTab name="prompts.schemas">Schemas</QTab>
			<QTab name="prompts.directives">Directives</QTab>
		</QTabs>

		<div class="h-full">
			<RouterView />
		</div>
	</div>
</template>
<script setup lang="ts">
import router from "@/router";
import { computed } from "vue";

const currentPage = computed(() => router.currentRoute.value.name);
</script>
