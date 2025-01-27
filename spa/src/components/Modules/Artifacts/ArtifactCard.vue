<template>
	<div>
		<div class="flex items-center mb-2">
			<div class="flex flex-nowrap items-center flex-grow">
				<div>{{ artifact.name }}</div>
			</div>
			<div>{{ fDateTime(artifact.created_at) }}</div>
		</div>
		<div v-if="artifact.files.length > 0">
			<div class="flex items-stretch justify-start">
				<FilePreview
					v-for="file in artifact.files"
					:key="'file-upload-' + file.id"
					class="cursor-pointer bg-gray-200 w-16 h-16 m-1"
					:file="file"
					:related-files="artifact.files"
					downloadable
				/>
			</div>
		</div>
		<div>
			<MarkdownEditor
				:model-value="artifact.text_content || artifact.json_content"
				:format="artifact.text_content ? 'text' : 'yaml'"
				readonly
			/>
		</div>
	</div>
</template>
<script setup lang="ts">
import MarkdownEditor from "@/components/MarkdownEditor/MarkdownEditor";
import { Artifact } from "@/types";
import { fDateTime, FilePreview } from "quasar-ui-danx";

defineProps<{
	artifact: Artifact
}>();
</script>
