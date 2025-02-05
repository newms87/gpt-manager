<template>
	<QBtn class="text-xs bg-lime-900 text-lime-300">
		{{ fCurrency(usage.total_cost) }}
		<QMenu>
			<div class="grid grid-cols-2 p-6 gap-y-4 gap-x-4">
				<LabelValueFormat v-if="showRuns" label="Runs" :value="usage.count" />
				<LabelValueFormat
					:class="{'cols-2': !showRuns}"
					label="Total Cost"
					:value="fCurrency(usage.total_cost, {
						minimumFractionDigits: 4,
						maximumFractionDigits: 4
					})"
				/>
				<LabelValueFormat
					label="Input Cost" :value="fCurrency(usage.input_cost, {
						minimumFractionDigits: 4,
						maximumFractionDigits: 4
					})"
				/>
				<LabelValueFormat
					label="Output Cost" :value="fCurrency(usage.output_cost, {
						minimumFractionDigits: 4,
						maximumFractionDigits: 4
					})"
				/>
				<LabelValueFormat label="Input Tokens" :value="fNumber(usage.input_tokens)" />
				<LabelValueFormat label="Output Tokens" :value="fNumber(usage.output_tokens)" />
			</div>
		</QMenu>
	</QBtn>
</template>
<script setup lang="ts">
import { TaskUsage } from "@/types";
import { fCurrency, fNumber, LabelValueFormat } from "quasar-ui-danx";

defineProps<{
	usage: TaskUsage;
	showRuns?: boolean;
}>();
</script>
