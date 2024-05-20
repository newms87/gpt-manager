<template>
	<div class="p-3">
		<template v-if="job.dependencies">
			<WorkflowJobDependencyItem
				v-for="dependency in job.dependencies"
				:key="'dependency-' + dependency.id"
				:dependency="dependency"
				:job="job"
				class="mb-2"
				@update="setDependenciesAction.trigger(job, job.dependencies.map(dep => dep.id === dependency.id ? $event : dep))"
				@remove="setDependenciesAction.trigger(job, job.dependencies.filter(dep => dep.id !== dependency.id))"
			/>
		</template>
		<div v-if="dependencyOptions.length > 0" class="flex items-center flex-nowrap">
			<SelectField
				v-model="selectedDependencyId"
				:options="dependencyOptions"
				class="mr-4 w-1/2"
			/>
			<QBtn
				class="bg-indigo-800 text-indigo-200"
				:disable="!selectedDependencyId || setDependenciesAction.isApplying"
				:loading="setDependenciesAction.isApplying"
				@click="setDependenciesAction.trigger(job, [...(job.dependencies || []), {depends_on_id: selectedDependencyId, group_by: ''}])"
			>Add Dependency
			</QBtn>
		</div>
	</div>
</template>
<script setup lang="ts">
import { getAction } from "@/components/Modules/Workflows/workflowActions";
import WorkflowJobDependencyItem from "@/components/Modules/Workflows/WorkflowJobs/WorkflowJobDependencyItem";
import { Workflow, WorkflowJob } from "@/types/workflows";
import { SelectField } from "quasar-ui-danx";
import { computed, ref } from "vue";

const props = defineProps<{
	job: WorkflowJob;
	workflow: Workflow;
}>();

const setDependenciesAction = getAction("set-dependencies");

const selectedDependencyId = ref(null);
const dependencyOptions = computed(() => props.workflow.jobs.filter(job => job.id !== props.job.id && !props.job.dependencies?.find(d => d.depends_on_id === job.id))
	.map((job) => ({
		label: job.name,
		value: job.id
	})));
</script>
