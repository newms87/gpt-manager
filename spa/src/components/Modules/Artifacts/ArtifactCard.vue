<template>
	<div class="bg-slate-900 p-2 rounded-lg">
		<div class="flex items-center mb-2 space-x-2">
			<LabelPillWidget :label="`Artifact: ${artifact.id}`" color="sky" size="xs" />
			<LabelPillWidget :label="fDateTime(artifact.created_at)" color="blue" size="xs" />
			<div class="flex-grow">{{ artifact.name }}</div>
			<ShowHideButton v-model="isShowing" class="bg-sky-900" />
		</div>
		<ListTransition>
			<div v-if="artifact.files?.length && isShowing">
				<div class="flex items-stretch justify-start">
					<FilePreview
						v-for="file in artifact.files"
						:key="'file-upload-' + file.id"
						class="cursor-pointer bg-gray-200 w-16 h-16 m-1"
						:file="file"
						:related-files="file.transcodes"
						downloadable
					/>
				</div>
			</div>
			<div v-if="artifact.text_content && isShowing">
				<MarkdownEditor
					:model-value="artifact.text_content"
					format="text"
					readonly
				/>
			</div>
			<div v-if="artifact.json_content && isShowing">
				<MarkdownEditor
					:model-value="artifact.json_content"
					format="yaml"
					readonly
				/>
			</div>
		</ListTransition>
	</div>
</template>
<script setup lang="ts">
import MarkdownEditor from "@/components/MarkdownEditor/MarkdownEditor";
import { Artifact } from "@/types";
import { fDateTime, FilePreview, LabelPillWidget, ListTransition, ShowHideButton } from "quasar-ui-danx";
import { ref, watch } from "vue";

const props = defineProps<{
	artifact: Artifact,
	show?: boolean;
}>();

const isShowing = ref(false);
watch(() => props.show, (show) => isShowing.value = show);
</script>
