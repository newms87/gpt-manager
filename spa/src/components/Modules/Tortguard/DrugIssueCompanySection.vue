<template>
	<div class="drug-issue-company p-4 rounded-lg">
		<LogoImage v-if="company.meta?.logo" :src="company.meta?.logo" class="max-w-24 max-h-10" :url="company.url" />

		<h6 class="mb-2">{{ company.name }}</h6>

		<div class="grid grid-cols-3 gap-4">
			<TeamObjectAttributeBlock
				v-if="company.parent"
				label="Parent"
				:attribute="company.parent.name"
				:url="company.parent.url"
			/>
			<TeamObjectAttributeBlock
				label="Annual Revenue"
				format="shortCurrency"
				:attribute="company.annual_revenue"
			/>
			<TeamObjectAttributeBlock
				label="Operating Income"
				:attribute="company.operating_income"
				format="shortCurrency"
			/>
			<TeamObjectAttributeBlock
				label="Net Income"
				:attribute="company.net_income"
				format="shortCurrency"
			/>
			<TeamObjectAttributeBlock
				label="Total Assets"
				:attribute="company.total_assets"
				format="shortCurrency"
			/>
			<TeamObjectAttributeBlock
				label="Total Equity"
				:attribute="company.total_equity"
				format="shortCurrency"
			/>
		</div>
	</div>
</template>

<script setup lang="ts">
import TeamObjectAttributeBlock from "@/components/Modules/Tortguard/TeamObjectAttributeBlock";
import { Company } from "@/components/Modules/Tortguard/tortguard";
import LogoImage from "@/components/Shared/Images/LogoImage";

defineProps<{ company: Company }>();
</script>
