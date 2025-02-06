<template>
	<ListTransition>
		<QSkeleton v-if="!artifacts" class="h-20 my-2" />
		<div v-else-if="artifacts.length === 0" class="text-xl text-center text-gray-500">No Artifacts</div>
		<template v-else>
			<ArtifactCard v-for="artifact in artifacts" :key="artifact.id" class="mb-6" :artifact="artifact" />
		</template>
	</ListTransition>
</template>
<script setup lang="ts">
import ArtifactCard from "@/components/Modules/Artifacts/ArtifactCard";
import { Artifact } from "@/types";
import { ListTransition } from "quasar-ui-danx";

defineProps<{
	artifacts?: Artifact[];
}>();
</script>
