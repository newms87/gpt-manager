<template>
	<div class="artifact-split-mode-widget">
		<div class="flex-x gap-4">
			<div class="font-bold">Parallelization:</div>
			<QTabs
				v-model="splitMode"
				class="tab-buttons border-sky-900"
				indicator-color="sky-900"
			>
				<QTab name="" label="All Together" />
				<QTab name="Node" label="Per Task" />
				<QTab name="Artifact" label="Individual" />
				<QTab name="Combinations" label="All Combinations" />
			</QTabs>
			<div>
				<template v-if="splitMode === 'Node'">
					Run one process per task in parallel. Each process receives all the artifacts for that specific task.
				</template>
				<template v-else-if="splitMode === 'Artifact'">
					Run one process per artifact in parallel. Each process receives one artifact for the task.
				</template>
				<template v-else-if="splitMode === 'Combinations'">
					Run one process for each combination of artifacts across tasks (cross-product).<br />
					For example, if Task A has artifacts 1, 2, 3 and Task B has artifacts 4, 5, this will run 6 processes: (1,4),
					(1,5), (2,4), (2,5), (3,4), and (3,5).
				</template>
				<template v-else>
					Run a single process with all artifacts from all tasks grouped together.
				</template>
			</div>

		</div>
	</div>
</template>
<script setup lang="ts">
import { ArtifactSplitMode } from "@/types";

const splitMode = defineModel<ArtifactSplitMode>();
</script>
