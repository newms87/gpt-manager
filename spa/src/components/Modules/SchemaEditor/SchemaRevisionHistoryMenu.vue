<template>
	<div>
		<QBtn class="bg-sky-900" @click="loadHistory">
			<HistoryIcon class="w-4" />
			<QMenu>
				<div class="flex flex-col max-h-[50rem] flex-nowrap p-4">
					<div class="flex-x text-xl font-bold pb-4">
						<HistoryIcon class="w-4 mr-3" />
						Revision History
					</div>

					<QSeparator class="bg-slate-600 mb-4" />

					<div class="overflow-y-auto overflow-x-hidden flex-grow">
						<div
							v-for="revision in history"
							:key="revision.id"
							class="flex-x py-2 px-4 cursor-pointer my-2 mx-4 rounded-xl"
							:class="isMatch(revision) ? 'bg-green-900' : 'bg-slate-600 hover:bg-slate-700'"
							@click="$emit('select', revision)"
						>
							<div class="font-bold text-sky-400 w-32">{{ fDateTime(revision.created_at) }}</div>
							<div class="text-xs text-slate-400 whitespace-nowrap">{{ revision.user_email }}</div>
						</div>
					</div>
				</div>
			</QMenu>
		</QBtn>

	</div>
</template>

<script setup lang="ts">
import { routes } from "@/components/Modules/Schemas/SchemaDefinitions/config/routes";
import { SchemaDefinition, SchemaDefinitionRevision } from "@/types";
import { FaSolidClock as HistoryIcon } from "danx-icon";
import { fDateTime } from "quasar-ui-danx";
import { ref } from "vue";

defineEmits(["select"]);
const props = defineProps<{
	schemaDefinition: SchemaDefinition
}>();

const history = ref<SchemaDefinitionRevision[]>(null);
async function loadHistory() {
	history.value = await routes.history(props.schemaDefinition);
}

function isMatch(revision) {
	return JSON.stringify(revision.schema) === JSON.stringify(props.schemaDefinition.schema);
}

</script>
