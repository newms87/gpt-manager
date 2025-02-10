<template>
	<div class="p-6 h-full overflow-y-auto">
		<template v-for="agent in schemaDefinition.agents" :key="agent.id">
			<QSeparator class="bg-slate-400 my-4" />
			<div>
				{{ agent.name }}
			</div>
		</template>
	</div>
</template>
<script setup lang="ts">
import { SchemaDefinition } from "@/types";

defineProps<{
	schemaDefinition: SchemaDefinition,
}>();
</script>
