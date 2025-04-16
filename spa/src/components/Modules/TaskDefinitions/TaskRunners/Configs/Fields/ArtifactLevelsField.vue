<template>
	<div>
		<div class="mt-8">
			<div class="font-bold mb-2 flex-x gap-2">
				<div v-if="mode === 'input'">Accepted Artifact Level(s)</div>
				<div v-else>Maximum Artifact Level</div>
				<div class="relative">
					<HelpIcon class="w-4" />
					<QTooltip>
						<div class="text-sm text-gray-100 space-y-1">
							<template v-if="mode === 'input'">
								<p><span class="font-semibold">Artifact Grouping Level:</span> Choose which level(s) of the artifact
									hierarchy to send to this task.</p>
								<p><span class="text-blue-200">Default:</span> Only top-level artifacts are used.</p>
								<p>Selecting lower levels will include child artifacts (e.g., pages within sections) instead of their
									parent groups.</p>
								<p>You may select multiple levels; all selected artifacts will be flattened before being processed.</p>
							</template>
							<template v-else>
								<p><span class="font-semibold">Artifact Grouping Level:</span> Choose the maximum level of the artifact
									hierarchy to be kept on output artifacts.</p>
								<p><span class="text-blue-200">Default:</span> Only top-level artifacts are used.</p>
								<p>Selecting lower levels will allow nested artifacts in the output up to the selected level.</p>
							</template>
						</div>
					</QTooltip>
				</div>
			</div>
		</div>
		<SelectField
			:model-value="levels"
			multiple
			:options="groupLevelOptions"
			@update="validateSelection"
		/>
	</div>
</template>
<script setup lang="ts">
import { useDebounceFn } from "@vueuse/core";
import { FaSolidCircleInfo as HelpIcon } from "danx-icon";
import { SelectField } from "quasar-ui-danx";
import { onMounted } from "vue";

const props = defineProps<{ mode: "input" | "output" }>();

const levels = defineModel<number[]>("levels", {
	default: [0],
	validator: (value) => Array.isArray(value) && value.every((v) => typeof v === "number")
});

const groupLevelOptions = [
	{ label: "Top Level", value: 0 },
	...(new Array(10).fill(0).map((_, i) => ({ label: `Level ${i + 1}`, value: i + 1 })))
];

const validateSelection = useDebounceFn((selection: number[]) => {
	// Make sure the selection contains at least 1 entry (default to the top-level)
	selection = (selection || []).length === 0 ? [0] : selection;

	if (props.mode === "output") {
		selection = selection.length > 1 ? [selection[selection.length - 1]] : selection;
	}

	if (levels.value?.length !== selection.length) {
		levels.value = selection;
	}
}, 10);

onMounted(() => validateSelection(levels.value));
</script>
