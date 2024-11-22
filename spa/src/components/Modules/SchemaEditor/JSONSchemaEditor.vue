<template>
	<div class="overflow-auto pb-8">
		<SchemaObject v-model="schema" class="min-w-64" />
	</div>
</template>
<script setup lang="ts">
import SchemaObject from "@/components/Modules/SchemaEditor/SchemaObject";
import { JsonSchema } from "@/types";

const schema = defineModel<JsonSchema>();
</script>
