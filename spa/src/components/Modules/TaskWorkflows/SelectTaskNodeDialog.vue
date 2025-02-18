<template>
	<InfoDialog title="Select Task node" @close="$emit('close')">
		<div
			v-for="taskDefinition in taskDefinitions"
			:key="taskDefinition.id"
		>
			<TaskDefinitionCard
				:task-definition="taskDefinition"
				class="cursor-pointer"
				@click="$emit('confirm', taskDefinition)"
			/>
			<QSeparator class="my-4 bg-slate-400" />
		</div>
		<div v-if="isLoading">
			<QSkeleton v-for="i in 3" :key="'loading-' + i" class="h-20" />
		</div>
	</InfoDialog>
</template>
<script setup>
import { dxTaskDefinition } from "@/components/Modules/TaskDefinitions";
import { InfoDialog } from "quasar-ui-danx";
import { onMounted, ref } from "vue";
import TaskDefinitionCard from "./TaskDefinitionCard.vue";

defineEmits(["confirm", "close"]);

onMounted(loadTaskDefinitions);

const isLoading = ref(false);
const taskDefinitions = ref([]);

async function loadTaskDefinitions() {
	isLoading.value = true;
	const results = await dxTaskDefinition.routes.list();

	taskDefinitions.value = results.data;
	isLoading.value = false;
}
</script>
