<template>
	<div class="relative h-full">
		<TortguardDashboard v-if="authTeam.namespace === 'tortguard'" />
		<OnDemandsDashboard v-if="authTeam.namespace === 'on-demands'" />
	</div>
</template>
<script setup lang="ts">
import OnDemandsDashboard from "@/components/OnDemands/OnDemandsDashboard";
import TortguardDashboard from "@/components/Tortguard/TortguardDashboard";
import { authTeam } from "@/helpers";
</script>
