<template>
	<div class="drug-issue-studies">
		<ShowHideButton v-model="isShowing" :label="'Scientific Studies: ' + studies.length" class="bg-green-800" />

		<div v-if="isShowing" class="mt-4">
			<div v-for="study in studies" :key="study.id" class="bg-slate-800 p-3 rounded-lg mb-4">
				<div class="text-lg font-bold">
					<a :href="study.url" target="_blank">{{ study.name }}</a>
				</div>
				<div class="text-sm mt-2">{{ study.description }}</div>
				<div class="flex items-stretch mt-4">
					<div class="grid grid-cols-3 gap-4">
						<LabelValueBlock label="Quality Grade" :value="study.quality_grade" />
						<LabelValueBlock label="Injury" :value="study.injury" />
						<LabelValueBlock label="Group Size" :value="study.group_size ? fNumber(study.group_size) : '-'" />
						<LabelValueBlock label="Age Range" :value="study.age_range" />
						<LabelValueBlock label="Median Age" :value="study.median_age" />
					</div>
					<div class="flex-grow ml-8">
						<div>
							<div class="font-bold">Treatment Method</div>
							<div class="mt-2">{{ study.treatment_method }}</div>
						</div>
						<div class="mt-4">
							<div class="font-bold">Treatment Efficacy</div>
							<div class="mt-2">{{ study.treatment_efficacy }}</div>
						</div>
						<div class="mt-4">
							<div class="font-bold">Complications</div>
							<div class="mt-2">{{ study.complications }}</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</template>
<script setup lang="ts">
import { ScientificStudy } from "@/components/Modules/Tortguard/drugs";
import { ShowHideButton } from "@/components/Shared";
import { fNumber, LabelValueBlock } from "quasar-ui-danx";
import { ref } from "vue";

defineProps<{
	studies: ScientificStudy[];
}>();
const isShowing = ref(false);

</script>
