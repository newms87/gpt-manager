<template>
	<div class="py-2">
		<ListTransition>
			<template v-for="assignment in assignments" :key="assignment.id">
				<QSeparator class="bg-slate-200" />

				<div class="py-3 flex items-center">
					<div class="flex-grow">
						<template v-if="context === 'agent'">
							<div>{{ assignment.workflowJob.workflow.name }}</div>
							<div class="font-bold ml-2">{{ assignment.workflowJob.name }}</div>
						</template>
						<template v-if="context === 'workflow'">
							<div class="font-bold">{{ assignment.agent.name }}</div>
							<div class="ml-2 text-xs">{{ assignment.agent.model }}</div>
						</template>
					</div>
					<ActionButton
						:action="unassignAction"
						:target="assignment"
						type="trash"
					/>
				</div>
			</template>
		</ListTransition>
	</div>
</template>
<script setup lang="ts">
import ActionButton from "@/components/Shared/Buttons/ActionButton";
import { WorkflowAssignment } from "@/types/workflows";
import { ListTransition, ResourceAction } from "quasar-ui-danx";

defineProps<{
	assignments: WorkflowAssignment[];
	unassignAction: ResourceAction;
	context: "workflow" | "agent";
}>();
</script>
