<template>
	<QBtn class="text-xs bg-lime-900 text-lime-300">
		{{ fCurrency(usage.total_cost) }}
		<QMenu>
			<div class="grid grid-cols-2 p-6 gap-y-4 gap-x-4">
				<LabelValueFormat label="Runs" :value="usage.count" />
				<LabelValueFormat
					label="Cost" :value="fCurrency(usage.total_cost, {
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
import { WorkflowUsage } from "@/types/workflows";
import { fCurrency, fNumber, LabelValueFormat } from "quasar-ui-danx";

defineProps<{
	usage: WorkflowUsage;
}>();
</script>
