<template>
	<UiMainLayout>
		<template #header>
			<div class="px-6 py-4">
				<div class="flex items-center justify-between mb-4">
					<div>
						<h1 class="text-2xl font-bold text-slate-800">
							Insurance Demands
						</h1>
						<p class="text-slate-600 mt-1">
							Manage and track your demand submissions
						</p>
					</div>

					<ActionButton
						type="create"
						size="lg"
						color="sky"
						rounded
						label="New Demand"
						@click="showCreateModal = true"
					/>
				</div>

				<!-- Stats Cards -->
				<DemandStatsCards
					:selected-status="selectedStatus"
					@filter-change="handleFilterChange"
				/>
			</div>
		</template>

		<!-- Main Content -->
		<div class="space-y-6">
			<!-- Demands List -->
			<DemandsList
				:status-filter="selectedStatus"
				@create="showCreateModal = true"
				@view="viewDemand"
				@edit="editDemand"
			/>
		</div>

		<!-- Create Demand Modal -->
		<CreateDemandModal
			:is-open="showCreateModal"
			@close="showCreateModal = false"
		/>
	</UiMainLayout>
</template>

<script setup lang="ts">
import { ActionButton } from "quasar-ui-danx";
import { onMounted, ref } from "vue";
import { useRouter } from "vue-router";
import { UiMainLayout } from "../../shared";
import type { UiDemand } from "../../shared/types";
import { CreateDemandModal, DemandsList, DemandStatsCards } from "../components";
import { useDemands } from "../composables";

const router = useRouter();
const { loadDemands } = useDemands();

const showCreateModal = ref(false);
const selectedStatus = ref<string | undefined>();

const handleFilterChange = (status: string | undefined) => {
	selectedStatus.value = status;
};

const viewDemand = (demand: UiDemand) => {
	router.push(`/ui/demands/${demand.id}`);
};

const editDemand = (demand: UiDemand) => {
	router.push(`/ui/demands/${demand.id}/edit`);
};

onMounted(() => {
	loadDemands();
});
</script>
