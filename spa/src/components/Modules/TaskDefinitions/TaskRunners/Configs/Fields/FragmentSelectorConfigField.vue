<template>
	<div>
		<MarkdownEditor
			:model-value="fragmentSelector"
			format="yaml"
			editor-class=""
			@update:model-value="debounceUpdate"
		/>
	</div>
</template>
<script setup lang="ts">
import { MarkdownEditor } from "@/components/MarkdownEditor";
import { FragmentSelector } from "@/types";
import { useDebounceFn } from "@vueuse/core";

const props = withDefaults(defineProps<{ delay?: number }>(), {
	delay: 0
});
const fragmentSelector = defineModel<FragmentSelector | string | null>();

const debounceUpdate = useDebounceFn((value) => fragmentSelector.value = value, props.delay);
</script>
