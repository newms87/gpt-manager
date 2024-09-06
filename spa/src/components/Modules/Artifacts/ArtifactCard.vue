<template>
	<div>
		<div class="flex items-center mb-2">
			<div class="flex flex-nowrap items-center flex-grow">
				<div>{{ artifact.name }}</div>
			</div>
			<div>{{ fDateTime(artifact.created_at) }}</div>
		</div>
		<div>
			<MarkdownEditor
				:model-value="artifact.content || artifact.data"
				:format="artifact.content ? 'text' : 'yaml'"
				readonly
			/>
		</div>
	</div>
</template>
<script setup lang="ts">
import MarkdownEditor from "@/components/MarkdownEditor/MarkdownEditor";
import { Artifact } from "@/types";
import { fDateTime } from "quasar-ui-danx";

defineProps<{
	artifact: Artifact
}>();
</script>
