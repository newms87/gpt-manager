<template>
	<div>
		<div class="flex items-center mb-2">
			<div class="flex flex-nowrap items-center flex-grow">
				<div>{{ artifact.name }}</div>
				<div class="text-slate-300 mx-3 bg-slate-500 rounded py-.5 px-2">
					Group: {{ artifact.group || "(default)" }}
				</div>
			</div>
			<div>{{ fDateTime(artifact.created_at) }}</div>
		</div>
		<div>
			<MarkdownEditor :model-value="artifact.content" readonly />
		</div>
	</div>
</template>
<script setup lang="ts">
import MarkdownEditor from "@/components/MardownEditor/MarkdownEditor";
import { Artifact } from "@/types";
import { fDateTime } from "quasar-ui-danx";

defineProps<{
	artifact: Artifact
}>();
</script>
