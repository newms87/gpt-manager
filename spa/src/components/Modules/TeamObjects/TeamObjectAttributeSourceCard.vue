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
import LabelValuePillWidget from "@/components/Shared/Widgets/LabelValuePillWidget";
import { FilePreview } from "quasar-ui-danx";

defineProps<TeamObjectAttributeSourceCardProps>();
</script>
