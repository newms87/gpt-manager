<template>
	<div class="artifact-split-mode-widget">
		<div class="flex-x gap-4">
			<div class="font-bold">Parallelization:</div>
			<QTabs
				v-model="splitMode"
				class="tab-buttons border-sky-900 text-sky-200"
				indicator-color="sky-900"
			>
				<QTab name="" label="All Together" />
				<QTab name="Node" label="Per Task" />
				<QTab name="Top-Level" label="Per Top Level" />
				<QTab name="Artifact" label="Per Artifact" />
				<QTab name="Combinations" label="All Combinations" />
			</QTabs>
			<div>
				<template v-if="splitMode === 'Node'">
					Run one process per task in parallel. Each process receives all the artifacts for that specific task.
				</template>
				<template v-else-if="splitMode === 'Artifact'">
					Run one process per artifact in parallel. Each process receives one artifact for the task.
				</template>
				<template v-else-if="splitMode === 'Top-Level'">
					Run one process per top-level artifact in parallel. Each process receives all the artifacts in the selected
					levels under the top-level artifact.
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
		<div class="mt-8">
			<div class="font-bold mb-2 flex-x gap-2">
				<div>Artifact Level</div>
				<div class="relative">
					<HelpIcon class="w-4" />
					<QTooltip>
						<div class="text-sm text-gray-100 space-y-1">
							<p><span class="font-semibold">Artifact Grouping Level:</span> Choose which level(s) of the artifact
								hierarchy to send to this task.</p>
							<p><span class="text-blue-200">Default:</span> Only top-level artifacts are used.</p>
							<p>Selecting lower levels will include child artifacts (e.g., pages within sections) instead of their
								parent groups.</p>
							<p>You may select multiple levels; all selected artifacts will be flattened before being processed.</p>
						</div>
					</QTooltip>
				</div>
			</div>
			<div>
				<SelectField
					v-model="levels"
					:options="groupLevelOptions"
					multiple
					@update="validateSelection"
				/>
			</div>
		</div>
	</div>
</template>
<script setup lang="ts">
import { ArtifactSplitMode } from "@/types";
import { FaSolidCircleQuestion as HelpIcon } from "danx-icon";
import { SelectField } from "quasar-ui-danx";
import { nextTick, onMounted } from "vue";

const splitMode = defineModel<ArtifactSplitMode>();
const levels = defineModel<number[]>("levels", {
	default: [0],
	validator: (value) => Array.isArray(value) && value.every((v) => typeof v === "number")
});

const groupLevelOptions = [
	{ label: "Top Level", value: 0 },
	...(new Array(10).fill(0).map((_, i) => ({ label: `Level ${i + 1}`, value: i + 1 })))
];

function validateSelection() {
	nextTick(() => {
		if (!levels.value?.length) {levels.value = [0];}
	});
}
onMounted(validateSelection);
</script>
