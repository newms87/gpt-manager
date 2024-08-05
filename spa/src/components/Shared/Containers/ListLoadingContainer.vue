<template>
	<div class="dx-list-loading-container">
		<div v-if="empty" :class="emptyClass">{{ emptyText }}</div>
		<QSkeleton v-else-if="loading" :class="skeletonClass" :count="loadingCount" />
		<template v-else>
			<slot />
		</template>
	</div>
</template>
<script setup lang="ts">

export interface ListLoadingContainerProps {
	empty: boolean;
	loading: boolean;
	loadingCount?: number;
	emptyText?: string;
	skeletonClass?: string;
	emptyClass?: string;
}

withDefaults(defineProps<ListLoadingContainerProps>(), {
	loadingCount: 3,
	emptyText: "The list is empty.",
	skeletonClass: "my-3 py-3",
	emptyClass: "text-center text-slate-400"
});
</script>
