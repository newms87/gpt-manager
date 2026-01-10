<template>
	<div>
		<CodeViewer
			:model-value="fragmentSelector"
			format="yaml"
			can-edit
			@update:model-value="debounceUpdate"
		/>
	</div>
</template>
<script setup lang="ts">
import { CodeViewer } from "quasar-ui-danx";
import { FragmentSelector } from "@/types";
import { useDebounceFn } from "@vueuse/core";

const props = withDefaults(defineProps<{ delay?: number }>(), {
	delay: 0
});
const fragmentSelector = defineModel<FragmentSelector | string | null>();

const debounceUpdate = useDebounceFn((value) => fragmentSelector.value = value, props.delay);
</script>
