<template>
	<div class="drug-issue-patent mt-6">
		<ShowHideButton
			:label="'Patents: ' + patents.length"
			class="bg-sky-900"
			@click="isShowing = !isShowing"
		/>

		<template v-if="isShowing">
			<DrugPatentCard v-for="patent in patents" :key="patent.id" :patent="patent" class="mt-4" />
		</template>
	</div>
</template>

<script setup lang="ts">
import DrugPatentCard from "@/components/Modules/Tortguard/DrugPatentCard";
import { Patent } from "@/components/Modules/Tortguard/tortguard";
import { ShowHideButton } from "@/components/Shared";
import { ref } from "vue";

const isShowing = ref(false);
defineProps<{ patents: Patent[] }>();
</script>
