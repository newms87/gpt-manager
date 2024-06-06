<template>
	<div class="text-left">
		<template v-if="sources.length > 0">
			<a
				v-for="source in sources"
				:key="source.id"
				:href="source.url"
				target="_blank"
				class="flex items-center flex-nowrap"
			>
				<SourceLinkIcon class="w-4 mr-2" />
				{{ source.name }}
			</a>
		</template>
		<div v-else class="text-slate-400 text-sm">(No Sources)</div>
	</div>
</template>

<script setup lang="ts">
import { DataSource } from "@/components/Modules/Tortguard/drugs";
import { FaSolidLink as SourceLinkIcon } from "danx-icon";

defineProps<{ sources: DataSource[] }>();
</script>
