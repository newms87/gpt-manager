<template>
	<div>
		<div v-if="job.dependencies">
			<WorkflowJobDependencyItem
				v-for="dependency in job.dependencies"
				:key="'dependency-' + dependency.id"
				:dependency="dependency"
				class="mt-2"
				@update="setDependenciesAction.trigger(job, job.dependencies.map(dep => dep.id === dependency.id ? $event : dep))"
				@remove="setDependenciesAction.trigger(job, job.dependencies.filter(dep => dep.id !== dependency.id))"
			/>
		</div>
		<div v-if="dependencyOptions.length > 0" class="flex items-center flex-nowrap mt-2">
			<SelectField
				:options="dependencyOptions"
				class="mr-4 w-1/2"
				placeholder="+ Add Dependency"
				:clearable="false"
				:loading="setDependenciesAction.isApplying"
				:readonly="setDependenciesAction.isApplying"
				@update="onSelectDependency"
			/>
		</div>
	</div>
</template>
<script setup lang="ts">
import { dxWorkflow } from "@/components/Modules/Workflows";
import WorkflowJobDependencyItem from "@/components/Modules/Workflows/WorkflowJobs/WorkflowJobDependencyItem";
import { Workflow, WorkflowJob } from "@/types";
import { SelectField } from "quasar-ui-danx";
import { computed } from "vue";

const props = defineProps<{
	job: WorkflowJob;
	workflow: Workflow;
}>();

const setDependenciesAction = dxWorkflow.getAction("set-dependencies");

function isCircularDependency(dependencyJob: WorkflowJob) {
	if (dependencyJob.id === props.job.id) return true;
	if (!dependencyJob.dependencies) return false;
	return dependencyJob.dependencies.some(d => isCircularDependency(props.workflow.jobs.find(j => j.id === d.depends_on_id)));
}

function hasDependency(dependencyJob: WorkflowJob) {
	return props.job.dependencies?.some(d => d.depends_on_id === dependencyJob.id);
}

const dependencyOptions = computed(() => props.workflow.jobs.filter(job => !hasDependency(job) && !isCircularDependency(job))
	.map((job) => ({
		label: job.name,
		value: job.id
	})));

function onSelectDependency(depends_on_id) {
	if (depends_on_id) {
		setDependenciesAction.trigger(props.job, [...(props.job.dependencies || []), {
			depends_on_id,
			group_by: ""
		}]);
	}
}
</script>
