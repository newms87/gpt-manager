<template>
	<div class="p-3 bg-slate-800 rounded">
		<div class="team-object-header flex items-center flex-nowrap">
			<div class="flex-grow">
				<div class="font-bold flex items-center gap-2">
					{{ object.name }}
					<a v-if="object.url" target="_blank" :href="object.url">
						<LinkIcon class="w-4" />
					</a>
				</div>
				<div>{{ object.description }}</div>
			</div>
			<div>
				<ShowHideButton v-model="isShowing" label="Show" class="py-2 px-6 bg-sky-900" @show="onShow" />
			</div>
		</div>
		<div v-if="isShowing">
			{{ object }}
		</div>
	</div>
</template>
<script setup lang="ts">
import { TeamObjectRoutes } from "@/components/Modules/TeamObjects";
import { TeamObject } from "@/components/Modules/TeamObjects/team-objects";
import { FaSolidLink as LinkIcon } from "danx-icon";
import { ShowHideButton } from "quasar-ui-danx";
import { ref } from "vue";

const props = defineProps<{ object: TeamObject }>();

const isShowing = ref(false);

async function onShow() {
	await TeamObjectRoutes.detailsAndStore(props.object);
}
</script>
