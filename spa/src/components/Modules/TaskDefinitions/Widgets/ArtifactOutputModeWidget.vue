<template>
	<div class="flex-x gap-4">
		<div class="font-bold">Grouping:</div>
		<QTabs
			v-model="mode"
			class="tab-buttons border-green-900 !w-[20rem] bg-green-950 text-green-200"
			indicator-color="green-900"
		>
			<QTab name="">Standard</QTab>
			<QTab name="Per Process">Per Process</QTab>
			<QTab name="Group All">Group All</QTab>
		</QTabs>
		<div>
			<template v-if="mode === ''">
				Artifacts will not be grouped on output, unless the task itself performs grouping as part of its
				functionality.
			</template>

			<template v-else-if="mode === 'Per Process'">
				Each process within the task will group its output artifacts together.
				The result will be one grouped artifact per process.
			</template>

			<template v-else>
				All output artifacts from the task will be grouped into a single artifact.
				The result will be one grouped artifact for the entire task.
			</template>
		</div>
	</div>
</template>
<script setup lang="ts">
import { ArtifactOutputMode } from "@/types";

const mode = defineModel<ArtifactOutputMode>();
</script>
