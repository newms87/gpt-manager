<template>
	<ListTransition :class="dense ? 'space-y-2' : 'space-y-4'">
		<QSkeleton v-if="!artifacts" class="h-20 my-2" />
		<div v-else-if="artifacts.length === 0" class="text-xl text-center text-gray-500">No Artifacts</div>
		<template v-else>
			<div class="flex justify-end" :class="dense ? 'mb-4' : 'mb-8'">
				<ShowHideButton
					v-model="isShowingAll"
					class="bg-sky-900"
					icon-class="w-5"
					label-class="text-base ml-2"
					:label="isShowingAll ? 'Collapse All' : 'Expand All'"
				/>
			</div>
			<ArtifactCard
				v-for="artifact in artifacts"
				:key="artifact.id"
				:show="isShowingAll"
				:artifact="artifact"
			/>
		</template>
	</ListTransition>
</template>
<script setup lang="ts">
import ArtifactCard from "@/components/Modules/Artifacts/ArtifactCard";
import { Artifact } from "@/types";
import { ListTransition, ShowHideButton } from "quasar-ui-danx";
import { ref } from "vue";

defineProps<{
	artifacts?: Artifact[];
	dense?: boolean;
}>();

const isShowingAll = ref(false);
</script>
