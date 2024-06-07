<template>
	<div class="h-full p-8 overflow-y-auto">
		<DrugIssueCard v-for="drugIssue in drugIssues" :key="drugIssue.issue.id" :drug-issue="drugIssue" class="mb-8" />
	</div>
</template>
<script setup lang="ts">
import DrugIssueCard from "@/components/Modules/Tortguard/DrugIssueCard";
import { DrugIssue } from "@/components/Modules/Tortguard/drugs";
import { request } from "quasar-ui-danx";
import { onMounted, ref } from "vue";

const drugIssues = ref<DrugIssue[]>([]);

onMounted(async () => {
	drugIssues.value = await request.get("tortguard/dashboard");
});
</script>
