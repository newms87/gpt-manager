<template>
	<QBtn class="text-xs bg-lime-900 text-lime-300">
		{{ fCurrency(usage.cost) }}
		<QMenu>
			<QItem>Input Tokens: {{ fNumber(usage.input_tokens) }}</QItem>
			<QItem>Output Tokens: {{ fNumber(usage.output_tokens) }}</QItem>
			<QItem>Cost: {{
					fCurrency(usage.cost, {
						minimumFractionDigits: 4,
						maximumFractionDigits: 4
					})
				}}
			</QItem>
		</QMenu>
	</QBtn>
</template>
<script setup lang="ts">
import { WorkflowUsage } from "@/types/workflows";
import { fCurrency, fNumber } from "quasar-ui-danx";

defineProps<{
	usage: WorkflowUsage;
}>();
</script>
