<template>
	<div class="agent-thread-response-field flex-x space-x-4">
		<SchemaAndFragmentSelector
			v-model:editing="isEditingSchema"
			v-model="response.schema"
			v-model:fragment="response.fragment"
			can-select-schema
			can-select-fragment
			previewable
			can-edit-schema
			dialog
			hide-save-state
			:hide-default-header="response.format === 'text'"
		>
			<template #header-start>
				<QTabs
					v-model="response.format"
					dense
					class="tab-buttons border-sky-900"
					indicator-color="sky-900"
				>
					<QTab name="text" label="Text" />
					<QTab name="json_schema" label="JSON Schema" />
				</QTabs>
			</template>
		</SchemaAndFragmentSelector>
	</div>
</template>
<script setup lang="ts">
import SchemaAndFragmentSelector from "@/components/Modules/SchemaEditor/SchemaAndFragmentSelector.vue";
import { AgentThreadResponseFormat } from "@/types";
import { ref } from "vue";

const response = defineModel<AgentThreadResponseFormat>();

const isEditingSchema = ref(false);
</script>
