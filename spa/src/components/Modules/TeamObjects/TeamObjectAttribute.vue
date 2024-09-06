<template>
	<div :data-testid="name">
		<TeamObjectAttributeBlock
			:label="label || title || name"
			:format="format"
			:attribute="attribute"
		/>
	</div>
</template>
<script setup lang="ts">
import { TeamObjectAttributeProps } from "@/components/Modules/TeamObjects/team-objects";
import TeamObjectAttributeBlock from "@/components/Modules/Tortguard/TeamObjectAttributeBlock";

defineProps<TeamObjectAttributeProps>();
</script>
