<template>
	<div class="p-3 bg-slate-800 rounded">
		<div class="team-object-header flex items-center flex-nowrap gap-x-4">
			<div class="flex-grow">
				<div class="font-bold flex items-center gap-2">
					{{ object.name }}
					<a v-if="object.url" target="_blank" :href="object.url">
						<LinkIcon class="w-4" />
					</a>
				</div>
				<div>{{ object.description }}</div>
			</div>
			<ShowHideButton v-model="isShowing" label="Show" class="py-2 px-6 bg-sky-900" @show="onShow" />
			<QBtn
				class="p-3 bg-sky-900"
				:disable="editAction.isApplying"
				@click="editAction.trigger(object)"
			>
				<EditIcon class="w-4" />
			</QBtn>
			<QBtn
				class="p-3 bg-red-900"
				:disable="deleteAction.isApplying"
				@click="deleteAction.trigger(object)"
			>
				<DeleteIcon class="w-3.5" />
			</QBtn>
		</div>
		<div v-if="isShowing">
			{{ object }}
		</div>
	</div>
</template>
<script setup lang="ts">
import { dxTeamObject } from "@/components/Modules/TeamObjects";
import { TeamObject } from "@/components/Modules/TeamObjects/team-objects";
import { FaSolidLink as LinkIcon, FaSolidPencil as EditIcon, FaSolidTrash as DeleteIcon } from "danx-icon";
import { ShowHideButton } from "quasar-ui-danx";
import { ref } from "vue";

const props = defineProps<{ object: TeamObject }>();

const isShowing = ref(false);
const editAction = dxTeamObject.getAction("edit");
const deleteAction = dxTeamObject.getAction("delete");
async function onShow() {
	await dxTeamObject.routes.detailsAndStore(props.object);
}
</script>
