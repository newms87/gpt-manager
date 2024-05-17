<template>
	<div>
		<div class="flex items-center">
			<div>{{ artifact.name }}</div>
			<div>{{ artifact.group }}</div>
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
