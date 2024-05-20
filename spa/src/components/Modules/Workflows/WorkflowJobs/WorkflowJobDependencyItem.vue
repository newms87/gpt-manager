<template>
	<div class="flex items-center flex-nowrap bg-indigo-800 text-indigo-200 rounded-lg overflow-hidden">
		<div class="text-base font-bold flex-grow px-2 text-no-wrap text-ellipsis">{{ dependency.depends_on_name }}</div>
		<div class="ml-4 flex items-center flex-nowrap">
			<div class="mr-2">Group By</div>
			<TextField
				v-model="groupBy"
				no-label
				:debounce="1000"
				class="p-0"
				input-class="p-0"
				@update:model-value="$emit('update', {...dependency, group_by: groupBy})"
			/>
		</div>
		<TrashButton :saving="saving" class="p-4" @click="$emit('remove')" />
	</div>
</template>
<script setup lang="ts">
import TrashButton from "@/components/Shared/Buttons/TrashButton";
import { WorkflowJob, WorkflowJobDependency } from "@/types/workflows";
import { TextField } from "quasar-ui-danx";
import { ref } from "vue";

defineEmits(["update", "remove"]);
const props = defineProps<{
	dependency: WorkflowJobDependency;
	job: WorkflowJob;
	saving?: boolean;
}>();

const groupBy = ref(props.dependency.group_by);
</script>
