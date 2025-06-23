<template>
	<div class="min-h-screen bg-slate-900">
		<div class="container mx-auto px-4 py-8">
			<div class="flex items-center justify-between mb-8">
				<div class="flex items-center gap-3">
					<FaSolidComment class="w-8 h-8 text-green-400" />
					<div>
						<h1 class="text-2xl font-bold text-slate-100">WhatsApp Integration</h1>
						<p class="text-slate-400">Manage WhatsApp connections and messages</p>
					</div>
				</div>
			</div>

			<QTabs
				v-model="activeTab"
				class="text-slate-400"
				active-color="green"
				indicator-color="green"
				align="left"
			>
				<QTab name="connections" label="Connections" />
				<QTab name="messages" label="Messages" />
			</QTabs>

			<QTabPanels v-model="activeTab" class="mt-6">
				<QTabPanel name="connections" class="p-0">
					<WhatsAppConnectionList />
				</QTabPanel>

				<QTabPanel name="messages" class="p-0">
					<WhatsAppMessageList />
				</QTabPanel>
			</QTabPanels>
		</div>
	</div>
</template>

<script setup lang="ts">
import { FaSolidComment } from "danx-icon";
import { ref, onMounted, onUnmounted } from "vue";
import { useWhatsAppStore } from "@/components/Modules/WhatsApp/store";
import WhatsAppConnectionList from "@/components/Modules/WhatsApp/WhatsAppConnectionList.vue";
import WhatsAppMessageList from "@/components/Modules/WhatsApp/WhatsAppMessageList.vue";

const activeTab = ref('connections');
const { setupWhatsAppListeners } = useWhatsAppStore();

onMounted(() => {
	setupWhatsAppListeners();
});
</script>