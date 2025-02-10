<template>
	<div class="bg-slate-900 p-2 rounded-lg">
		<div class="flex items-center mb-2 space-x-2">
			<LabelPillWidget :label="`Artifact: ${artifact.id}`" color="sky" size="xs" />
			<LabelPillWidget :label="fDateTime(artifact.created_at)" color="blue" size="xs" />
			<div>{{ artifact.name }}</div>
		</div>
		<div v-if="artifact.files?.length">
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
		<div v-if="artifact.text_content || artifact.json_content">
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
import LabelPillWidget from "@/components/Shared/Widgets/LabelPillWidget";
import { Artifact } from "@/types";
import { fDateTime, FilePreview } from "quasar-ui-danx";

defineProps<{
	artifact: Artifact
}>();
</script>
