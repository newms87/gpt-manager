<template>
	<ActionTableLayout
		title="WhatsApp Connections"
		:controller="dxWhatsAppConnection"
		class="whatsapp-connections-list"
		create-button
		refresh-button
	/>
</template>

<script setup lang="ts">
import { ActionTableLayout } from "quasar-ui-danx";
import { dxWhatsAppConnection } from "./config";

dxWhatsAppConnection.initialize();
</script>

<style lang="scss" scoped>
.whatsapp-connections-list {
	.q-table {
		background: theme('colors.slate.800');
		color: theme('colors.slate.200');
	}
}
</style>