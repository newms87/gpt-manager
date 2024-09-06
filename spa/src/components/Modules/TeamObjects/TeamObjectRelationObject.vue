<template>
	<div :data-testid="name" class="bg-slate-900 py-2 px-4 rounded-lg">
		<div class="font-bold">{{ title || name }}</div>
		<TeamObjectCard v-if="object" :object="object" :schema="schema" />
		<div v-else>
			No {{ name }} found. Try creating a new one
		</div>
	</div>
</template>
<script setup lang="ts">
import { TeamObject } from "@/components/Modules/TeamObjects/team-objects";
import TeamObjectCard from "@/components/Modules/TeamObjects/TeamObjectCard";
import { JsonSchema } from "@/types";

defineProps<{ name: string, title?: string, object?: TeamObject, schema: JsonSchema }>();
</script>
