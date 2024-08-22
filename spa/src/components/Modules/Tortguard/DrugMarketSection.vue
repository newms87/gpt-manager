<template>
	<div class="drug-market-section p-4 bg-sky-950 rounded-lg">
		<h6 class="mb-2">
			Market
			<ShowHideButton v-model="expanded" :label="product.generics.length + ' Generics'" />
		</h6>
		<div class="grid grid-cols-6 gap-4">
			<TeamObjectAttributeBlock
				label="Market Share"
				:attribute="product.market_share"
			/>
			<TeamObjectAttributeBlock
				label="Number of Users"
				:attribute="product.number_of_users"
			/>
			<TeamObjectAttributeBlock
				label="Price Per Unit"
				:attribute="product.price_per_unit"
			/>
			<TeamObjectAttributeBlock
				label="Annual Revenue"
				:attribute="product.annual_revenue"
			/>
			<LabelValueBlock
				label="Generic Names"
				:value="product.genericNames?.map(g => g.name).join(', ')"
			/>
		</div>
		<div v-if="expanded">
			<div v-for="generic in product.generics" :key="generic.id" class="my-3 px-4 py-2 bg-slate-700 rounded-xl">
				<div class="mb-3 font-bold">
					<a :href="generic.url" target="_blank">{{ generic.name }}</a>
				</div>
				<div class="grid grid-cols-6 gap-4">
					<TeamObjectAttributeBlock
						label="Market Share"
						:attribute="generic.market_share"
					/>
					<TeamObjectAttributeBlock
						label="Number of Users"
						:attribute="generic.number_of_users"
					/>
					<TeamObjectAttributeBlock
						label="Price Per Unit"
						:attribute="generic.price_per_unit"
					/>
					<TeamObjectAttributeBlock
						label="Annual Revenue"
						:attribute="generic.annual_revenue"
					/>
				</div>
			</div>
		</div>
	</div>
</template>

<script setup lang="ts">
import TeamObjectAttributeBlock from "@/components/Modules/Tortguard/TeamObjectAttributeBlock";
import { DrugProduct } from "@/components/Modules/Tortguard/tortguard";
import { ShowHideButton } from "@/components/Shared";
import { LabelValueBlock } from "quasar-ui-danx";
import { ref } from "vue";

defineProps<{ product: DrugProduct }>();

const expanded = ref(false);
</script>
