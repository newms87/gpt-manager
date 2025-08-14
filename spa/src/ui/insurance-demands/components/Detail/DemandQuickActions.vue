<template>
	<UiCard>
		<template #header>
			<h3 class="text-lg font-semibold text-slate-800">
				Quick Actions
			</h3>
		</template>

		<div class="space-y-2">
			<ActionButton
				v-if="demand?.status === DEMAND_STATUS.DRAFT"
				type="edit"
				class="w-full justify-start"
				label="Edit Details"
				@click="$emit('edit')"
			/>

			<DemandActionButtons
				v-if="demand"
				:demand="demand"
				size="md"
				button-class="flex-col space-y-2 space-x-0"
				button-item-class="w-full justify-start"
				:loading-states="loadingStates"
				@extract-data="$emit('extract-data')"
				@write-demand="$emit('write-demand')"
			/>

			<ActionButton
				type="trash"
				class="w-full justify-start"
				label="Delete Demand"
				@click="$emit('delete')"
			/>
		</div>
	</UiCard>
</template>

<script setup lang="ts">
import { watchEffect } from "vue";
import { ActionButton } from "quasar-ui-danx";
import { UiCard } from "../../../shared";
import type { UiDemand } from "../../../shared/types";
import { DEMAND_STATUS } from "../../config";
import DemandActionButtons from "../DemandActionButtons.vue";

interface LoadingStates {
	extractData: boolean;
	writeDemand: boolean;
}

const props = defineProps<{
	demand: UiDemand | null;
	loadingStates: LoadingStates;
}>();

defineEmits<{
	"edit": [];
	"extract-data": [];
	"write-demand": [];
	"duplicate": [];
	"delete": [];
}>();

// Debug logging for DemandQuickActions state
watchEffect(() => {
	console.log('🔍 DemandQuickActions - Demand State Debug:', {
		demand_exists: !!props.demand,
		demand_id: props.demand?.id,
		demand_status: props.demand?.status,
		demand_status_enum: DEMAND_STATUS,
		is_draft: props.demand?.status === DEMAND_STATUS.DRAFT,
		loading_states: props.loadingStates,
		full_demand: props.demand
	});
	
	if (props.demand) {
		console.log('🔍 DemandQuickActions - Demand Object Details:', {
			can_extract_data: props.demand.can_extract_data,
			can_write_demand: props.demand.can_write_demand,
			metadata: props.demand.metadata,
			extract_data_completed_at: props.demand.metadata?.extract_data_completed_at,
			team_object_id: props.demand.team_object_id,
			is_extract_data_running: props.demand.is_extract_data_running,
			is_write_demand_running: props.demand.is_write_demand_running
		});
	}
});
</script>
