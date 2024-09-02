<template>
	<div class="drug-issue-studies">
		<ShowHideButton v-model="isShowing" :label="'Scientific Studies: ' + studies.length" class="bg-green-800" />

		<div v-if="isShowing" class="mt-4">
			<DrugScientificStudyCard v-for="study in studies" :key="study.id" :study="study" class="mt-4" />
		</div>
	</div>
</template>
<script setup lang="ts">
import DrugScientificStudyCard from "@/components/Modules/Tortguard/DrugScientificStudyCard";
import { ScientificStudy } from "@/components/Modules/Tortguard/tortguard";
import { ShowHideButton } from "quasar-ui-danx";
import { ref } from "vue";

defineProps<{
	studies: ScientificStudy[];
}>();
const isShowing = ref(false);
</script>
