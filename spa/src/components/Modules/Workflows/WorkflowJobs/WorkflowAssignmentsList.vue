<template>
	<div class="py-2">
		<ListTransition>
			<template v-for="assignment in assignments" :key="assignment.id">
				<QSeparator class="bg-slate-200" />

				<div class="py-3 flex items-center">
					<div class="flex-grow">
						<template v-if="assignment.workflowJob">
							<div class="font-bold">{{ assignment.workflowJob.workflow.name }}</div>
							<div class="ml-2">{{ assignment.workflowJob.name }}</div>
						</template>
						<template v-if="assignment.agent">
							<div class="font-bold">{{ assignment.agent.name }}</div>
							<div class="ml-2 text-xs">{{ assignment.agent.model }}</div>
						</template>
					</div>
					<ActionButton
						:action="unassignAgentAction"
						:target="assignment"
						type="trash"
					/>
				</div>
			</template>
		</ListTransition>
	</div>
</template>
<script setup lang="ts">
import { getAction } from "@/components/Modules/Workflows/workflowActions";
import ActionButton from "@/components/Shared/Buttons/ActionButton";
import { WorkflowAssignment } from "@/types/workflows";
import { ListTransition } from "quasar-ui-danx";

defineProps<{
	assignments: WorkflowAssignment[];
}>();

const unassignAgentAction = getAction("unassign-agent");
</script>
