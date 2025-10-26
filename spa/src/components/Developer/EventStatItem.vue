<template>
	<div class="bg-slate-800 rounded-lg p-4 border border-slate-700 hover:border-slate-600 transition-colors">
		<div class="flex items-center justify-between mb-2">
			<div class="flex items-center space-x-3 flex-1">
				<LabelPillWidget
					:label="resourceType"
					color="sky"
					size="xs"
				/>
				<LabelPillWidget
					:label="eventName"
					color="green"
					size="xs"
				/>
			</div>
			<span class="text-purple-400 font-bold text-lg">
				{{ count }}
			</span>
		</div>
		<div class="relative w-full h-2 bg-slate-900 rounded-full overflow-hidden">
			<div
				class="absolute top-0 left-0 h-full bg-gradient-to-r from-purple-600 to-purple-400 rounded-full transition-all duration-300"
				:style="{ width: `${percentage}%` }"
			/>
		</div>
	</div>
</template>

<script setup lang="ts">
import { LabelPillWidget } from "quasar-ui-danx";
import { computed } from "vue";

const props = defineProps<{
	resourceType: string;
	eventName: string;
	count: number;
	maxCount: number;
}>();

const percentage = computed(() => {
	if (props.maxCount === 0) return 0;
	return (props.count / props.maxCount) * 100;
});
</script>
