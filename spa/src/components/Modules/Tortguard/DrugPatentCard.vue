<template>
	<div class="bg-slate-900 p-4 rounded-xl">
		<div class="font-bold mb-4 text-base">
			<a :href="patent.url" target="_blank">{{ patent.description }}</a>
		</div>
		<div class="grid grid-cols-10 gap-4">
			<LabelValueBlock
				label="Patent Number"
				:value="patent.name"
				:url="patent.url"
			/>
			<TeamObjectAttributeBlock
				label="Legal Status"
				:attribute="patent.legal_status"
			/>
			<TeamObjectAttributeBlock
				label="Filed Date"
				:attribute="patent.filed_date"
				format="date"
			/>
			<TeamObjectAttributeBlock
				label="Issued Date"
				:attribute="patent.issued_date"
				format="date"
			/>
			<TeamObjectAttributeBlock
				label="Priority Date"
				:attribute="patent.priority_date"
				format="date"
			/>
			<TeamObjectAttributeBlock
				label="Expiration Date"
				:attribute="patent.expiration_date"
				format="date"
			/>
			<div class="col-span-4" />
			<TeamObjectAttributeBlock
				label="Inventors"
				:attribute="patent.inventors"
				class="col-span-2"
			/>
			<TeamObjectAttributeBlock
				label="Owners"
				:attribute="patent.inventors"
				class="col-span-2"
			/>
			<TeamObjectAttributeBlock
				label="Claims"
				class="col-span-6"
				:attribute="patent.claims"
			/>
			<TeamObjectAttributeBlock
				label="Specification"
				class="col-span-10"
				:attribute="patent.specification"
			/>
		</div>
	</div>
</template>

<script setup lang="ts">
import TeamObjectAttributeBlock from "@/components/Modules/Tortguard/TeamObjectAttributeBlock";
import { Patent } from "@/components/Modules/Tortguard/tortguard";
import { LabelValueBlock } from "quasar-ui-danx";

defineProps<{ patent: Patent }>();
</script>
