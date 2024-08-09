<template>
	<div class="drug-issue-patent mt-6">
		<ShowHideButton
			:label="'Patents: ' + patents.length"
			class="bg-sky-900"
			@click="isShowing = !isShowing"
		/>

		<template v-if="isShowing">
			<div v-for="patent in patents" :key="patent.id">
				<div class="grid grid-cols-2 gap-4 mt-4">
					<LabelValueBlock
						label="Patent Number"
						:value="patent.name"
						:url="patent.url"
					/>
					<LabelValueBlock
						label="Filed"
						:value="fDate(patent.filed_date?.value)"
						:url="patent.filed_date?.source?.url"
					/>
					<LabelValueBlock
						label="Expiration"
						:value="fDate(patent.expiration_date?.value)"
						:url="patent.expiration_date?.source?.url"
					/>
					<LabelValueBlock
						label="Issued"
						:value="fDate(patent.issued_date?.value)"
						:url="patent.issued_date?.source?.url"
					/>
				</div>
				<div class="max-w-[17rem] mt-5 text-justify">
					<div>{{ patent.description }}</div>
				</div>
			</div>
		</template>
	</div>
</template>

<script setup lang="ts">
import { Patent } from "@/components/Modules/Tortguard/tortguard";
import { ShowHideButton } from "@/components/Shared";
import { fDate, LabelValueBlock } from "quasar-ui-danx";
import { ref } from "vue";

const isShowing = ref(false);
defineProps<{ patents: Patent[] }>();
</script>
