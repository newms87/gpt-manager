<template>
	<div class="space-y-2">
		<LabelValuePillWidget
			v-if="source.explanation"
			class="flex items-stretch"
			label="Explanation"
			label-class="w-20"
			:value="source.explanation"
		/>
		<div v-if="source.sourceFile">
			<a :href="source.sourceFile.url" target="_blank">{{ source.sourceFile.name }}</a>
		</div>

		<div v-if="source.sourceMessage" class="space-y-4">
			<ThreadMessageCard
				readonly
				:message="source.sourceMessage"
			/>
		</div>
		<div v-if="source.sourceFile">
			<FilePreview downloadable :file="source.sourceFile" class="w-32 h-32" />
		</div>
	</div>
</template>
<script setup lang="ts">
import ThreadMessageCard from "@/components/Modules/Agents/Threads/ThreadMessageCard";
import { TeamObjectAttributeSourceCardProps } from "@/components/Modules/TeamObjects/team-objects";
import { useStoredFileUpdates } from "@/composables/useStoredFileUpdates";
import { FilePreview, LabelValuePillWidget } from "quasar-ui-danx";
import { watch } from "vue";

const props = defineProps<TeamObjectAttributeSourceCardProps>();

// Subscribe to file updates for real-time transcoding progress
const { subscribeToFileUpdates } = useStoredFileUpdates();

// Subscribe to source file when it's available
watch(() => props.source.sourceFile, (sourceFile) => {
	if (sourceFile) {
		subscribeToFileUpdates(sourceFile);
	}
}, { immediate: true });
</script>
