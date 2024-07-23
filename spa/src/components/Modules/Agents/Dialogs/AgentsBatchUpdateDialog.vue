<template>
	<ConfirmDialog @confirm="$emit('confirm', input)" @close="$emit('close')">
		<RenderedForm
			class="w-96"
			v-model:values="input"
			:form="{fields: batchFields}"
		/>
	</ConfirmDialog>
</template>
<script setup lang="ts">
import { fields } from "@/components/Modules/Agents/agentFormFields";
import { ConfirmDialog, RenderedForm } from "quasar-ui-danx";
import { computed, ref } from "vue";

defineEmits(["confirm", "close"]);
const input = ref({
	model: undefined,
	description: undefined,
	temperature: undefined
});

const batchFields = computed(() => fields.filter(f => ["model", "description", "temperature"].includes(f.name)));
</script>
