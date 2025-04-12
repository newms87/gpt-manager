<template>
	<div class="flex-x gap-4">
		<QTabs
			v-model="operator"
			class="tab-buttons w-[10rem] bg-sky-950 border-sky-900"
			indicator-color="sky-900"
		>
			<QTab name="AND" label="AND" />
			<QTab name="OR" label="OR" />
		</QTabs>
		<div>
			{{ operator === "AND" ? "All conditions must match" : "Any condition may match" }}
		</div>
	</div>
</template>
<script setup lang="ts">
const operator = defineModel<string>();
</script>
