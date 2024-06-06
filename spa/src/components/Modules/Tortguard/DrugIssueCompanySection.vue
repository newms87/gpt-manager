<template>
	<div class="drug-issue-company mt-6">
		<div class="flex justify-end">
			<LogoImage :src="company.logo" class="max-w-24 max-h-10" :url="company.url" />
		</div>
		<div class="grid grid-cols-2 gap-4 mt-4">
			<LabelValueBlock label="Company" :value="company.name" :url="company.url" />
			<LabelValueBlock
				label="Annual Revenue"
				:value="fShortCurrency(company.annual_revenue)"
				:url="getSourceUrl('companies', 'annual_revenue')"
			/>
			<LabelValueBlock
				label="Operating Income"
				:value="fShortCurrency(company.operating_income)"
				:url="getSourceUrl('companies', 'operating_income')"
			/>
			<LabelValueBlock
				label="Net Income"
				:value="fShortCurrency(company.net_income)"
				:url="getSourceUrl('companies', 'net_income')"
			/>
			<LabelValueBlock
				label="Total Assets"
				:value="fShortCurrency(company.total_assets)"
				:url="getSourceUrl('companies', 'total_assets')"
			/>
			<LabelValueBlock
				label="Total Equity"
				:value="fShortCurrency(company.total_equity)"
				:url="getSourceUrl('companies', 'total_equity')"
			/>
		</div>
	</div>
</template>

<script setup lang="ts">
import { Company } from "@/components/Modules/Tortguard/drugs";
import LogoImage from "@/components/Shared/Images/LogoImage";
import { fShortCurrency, LabelValueBlock } from "quasar-ui-danx";

defineProps<{ company: Company, getSourceUrl: (table, field) => string }>();
</script>
