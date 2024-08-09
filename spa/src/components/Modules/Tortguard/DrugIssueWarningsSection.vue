<template>
	<div class="drug-issue-studies">
		<ShowHideButton v-model="isShowing" :label="'Warnings: ' + warnings.length" class="bg-red-900" />

		<div v-if="isShowing" class="mt-4">
			<div v-for="warning in warnings" :key="warning.id" class="bg-slate-800 p-3 rounded-lg mb-4">
				<div class="text-lg font-bold flex items-center flex-nowrap">
					<div class="flex-grow">
						<a :href="warning.url" target="_blank">{{ warning.name }}</a>
					</div>
					<div class="bg-slate-900 text-slate-400 px-2 py-1 rounded-lg">{{ fDate(warning.issued_at.value) }}</div>
				</div>
				<div class="text-sm mt-2">{{ warning.description }}</div>
				<div v-if="warning.injury_risks" class="mt-4">
					<ul class="list-disc ml-8">
						<li v-for="injury in warning.injury_risks" :key="injury">{{ injury }}</li>
					</ul>
				</div>
			</div>
		</div>
	</div>
</template>
<script setup lang="ts">
import { DrugWarning } from "@/components/Modules/Tortguard/drugs";
import { ShowHideButton } from "@/components/Shared";
import { fDate } from "quasar-ui-danx";
import { ref } from "vue";

defineProps<{
	warnings: DrugWarning[];
}>();
const isShowing = ref(false);
</script>
