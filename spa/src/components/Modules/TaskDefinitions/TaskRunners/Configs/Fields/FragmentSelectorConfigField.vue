<template>
	<div>
		<MarkdownEditor
			v-model="fragmentSelector"
			format="yaml"
			editor-class=""
		/>
	</div>
</template>
<script setup lang="ts">
import { MarkdownEditor } from "@/components/MarkdownEditor";
import { FragmentSelector } from "@/types";

const fragmentSelector = defineModel<FragmentSelector | string | null>();
</script>
