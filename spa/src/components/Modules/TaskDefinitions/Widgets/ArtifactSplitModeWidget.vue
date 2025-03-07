<template>
	<div class="bg-sky-950 p-4 rounded">
		<div class="flex">
			<div class="pr-4 py-4">Artifact Split Mode:</div>
			<QTabs
				v-model="splitMode"
				class="tab-buttons border-sky-900"
				indicator-color="sky-900"
			>
				<QTab name="" label="Combine All" />
				<QTab name="Node" label="By Node" />
				<QTab name="Artifact" label="By Artifact" />
			</QTabs>
		</div>
	</div>
</template>
<script setup lang="ts">
import { ArtifactSplitMode } from "@/types";

const splitMode = defineModel<ArtifactSplitMode>();
</script>
