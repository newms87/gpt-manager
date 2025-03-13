<template>
	<div class="agent-thread-response-field flex items-center flex-nowrap space-x-4">
		<SchemaEditorToolbox
			v-model:isEditingSchema="isEditingSchema"
			v-model="response.schema"
			v-model:fragment="response.fragment"
			can-select
			can-select-fragment
			previewable
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
		</SchemaEditorToolbox>
	</div>
</template>
<script setup lang="ts">
import SchemaEditorToolbox from "@/components/Modules/SchemaEditor/SchemaEditorToolbox";
import { AgentThreadResponseFormat } from "@/types";
import { ref } from "vue";

const response = defineModel<AgentThreadResponseFormat>();

const isEditingSchema = ref(false);
</script>
