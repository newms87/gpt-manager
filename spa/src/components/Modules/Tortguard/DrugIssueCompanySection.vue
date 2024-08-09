<template>
	<div class="drug-issue-company">
		<LogoImage v-if="company.meta?.logo" :src="company.meta?.logo" class="max-w-24 max-h-10" :url="company.url" />

		<div class="grid grid-cols-3 gap-4">
			<LabelValueBlock label="Score" :value="company.score?.value" :url="company.url" />
			<LabelValueBlock
				label="Annual Revenue"
				:value="fShortCurrency(company.annual_revenue?.value)"
				:url="company.annual_revenue?.source?.url"
			/>
			<LabelValueBlock
				label="Operating Income"
				:value="fShortCurrency(company.operating_income?.value)"
				:url="company.operating_income?.source?.url"
			/>
			<LabelValueBlock
				label="Net Income"
				:value="fShortCurrency(company.net_income?.value)"
				:url="company.net_income?.source?.url"
			/>
			<LabelValueBlock
				label="Total Assets"
				:value="fShortCurrency(company.total_assets?.value)"
				:url="company.total_assets?.source?.url"
			/>
			<LabelValueBlock
				label="Total Equity"
				:value="fShortCurrency(company.total_equity?.value)"
				:url="company.total_equity?.source?.url"
			/>
		</div>
	</div>
</template>

<script setup lang="ts">
import { Company } from "@/components/Modules/Tortguard/tortguard";
import LogoImage from "@/components/Shared/Images/LogoImage";
import { fShortCurrency, LabelValueBlock } from "quasar-ui-danx";

defineProps<{ company: Company }>();
</script>
