<template>
	<div class="bg-sky-950 p-4 rounded">
		<div class="flex">
			<div class="pr-4 py-4">Artifact Split Mode:</div>
			<QTabs
				v-model="splitMode"
				class="tab-buttons border-sky-900"
				indicator-color="sky-900"
			>
				<QTab name="" label="All Together" />
				<QTab name="Node" label="Group By Task" />
				<QTab name="Artifact" label="One By One" />
				<QTab name="Combinations" label="All Combinations" />
			</QTabs>
		</div>
	</div>
</template>
<script setup lang="ts">
import { ArtifactSplitMode } from "@/types";

const splitMode = defineModel<ArtifactSplitMode>();
</script>
