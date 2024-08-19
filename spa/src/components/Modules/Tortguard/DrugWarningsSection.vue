<template>
	<div class="drug-warnings">
		<ShowHideButton v-model="isShowing" :label="'Warnings: ' + warnings.length" class="bg-red-900" />

		<div v-if="isShowing" class="mt-4">
			<DrugWarningCard v-for="warning in warnings" :key="warning.id" :warning="warning" class="mb-4" />
		</div>
	</div>
</template>
<script setup lang="ts">
import DrugWarningCard from "@/components/Modules/Tortguard/DrugWarningCard";
import { DrugWarning } from "@/components/Modules/Tortguard/tortguard";
import { ShowHideButton } from "@/components/Shared";
import { ref } from "vue";

defineProps<{
	warnings: DrugWarning[];
}>();
const isShowing = ref(false);
</script>
