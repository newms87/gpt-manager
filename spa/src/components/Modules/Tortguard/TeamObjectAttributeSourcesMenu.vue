<template>
	<div v-if="attribute.thread_url || attribute.reason || attribute.sources?.length > 0" class="inline-block ml-2">
		<LinkIcon class="w-4 cursor-pointer text-sky-500" />
		<QMenu class="p-4 mt-4 bg-slate-600">
			<div v-if="attribute.thread_url">
				<a :href="attribute.thread_url" target="_blank" class="flex items-center">
					<ThreadLinkIcon class="w-4 mr-2" />
					View Thread
				</a>
			</div>
			<div v-if="attribute.reason" class="mt-4 py-2 px-4 bg-slate-900 text-slate-400 rounded-lg text-base">
				{{ attribute.reason }}
			</div>

			<template v-for="source in attribute.sources" :key="source.id">
				<QSeparator class="my-4 bg-slate-300" />
				<TeamObjectAttributeSourceCard :source="source" />
			</template>
		</QMenu>
	</div>
</template>
<script setup lang="ts">
import { TeamObjectAttribute } from "@/components/Modules/TeamObjects/team-objects";
import TeamObjectAttributeSourceCard from "@/components/Modules/Tortguard/TeamObjectAttributeSourceCard";
import { FaSolidLink as LinkIcon, FaSolidMessage as ThreadLinkIcon } from "danx-icon";

const props = defineProps<{
	attribute: TeamObjectAttribute
}>();
</script>
