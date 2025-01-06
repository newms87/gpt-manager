<template>
	<div>
		<div v-if="source.location">
			{{ source.location }}
		</div>
		<div v-if="source.explanation" class="px-4 py-2 bg-slate-900 rounded-lg text-slate-400 text-base">
			{{ source.explanation }}
		</div>
		<div>
			<a v-if="source.sourceFile" :href="source.sourceFile.url" target="_blank">{{ source.sourceFile.name }}</a>
		</div>

		<div v-if="source.sourceMessage" class="mt-4 space-y-4">
			<ThreadMessageCard
				readonly
				:message="source.sourceMessage"
			/>
		</div>
	</div>
</template>
<script setup lang="ts">
import ThreadMessageCard from "@/components/Modules/Agents/Threads/ThreadMessageCard";
import { TeamObjectAttributeSourceCardProps } from "@/components/Modules/TeamObjects/team-objects";

const props = defineProps<TeamObjectAttributeSourceCardProps>();

console.log("attribute source", props.source);
</script>
