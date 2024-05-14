<template>
	<div id="primary-layout" class="min-w-xs h-full flex flex-col flex-nowrap bg-slate-900 text-slate-300">
		<ThePageHeader />

		<div class="flex items-stretch flex-nowrap flex-grow overflow-hidden">
			<CollapsableSidebar v-model:collapse="isCollapsed" min-width="5rem" name="primary-nav" class="primary-nav">
				<NavigationMenu
					:items="adminNavigation"
					:collapsed="isCollapsed"
					class="h-full"
					item-class="text-sky-700 hover:text-sky-100 hover:bg-sky-700"
				/>
			</CollapsableSidebar>
			<Transition
				mode="out-in"
				:duration="300"
			>
				<main :key="$route.name" class="flex-grow overflow-hidden">
					<slot />
				</main>
			</Transition>
		</div>
	</div>
</template>
<script setup lang="ts">
import ThePageHeader from "@/components/ThePageHeader";
import { adminNavigation } from "@/navigation";
import { useQuasar } from "quasar";
import { CollapsableSidebar, FlashMessages, NavigationMenu } from "quasar-ui-danx";
import { onMounted, ref } from "vue";

const isCollapsed = ref(false);

onMounted(() => {
	FlashMessages.notify = useQuasar().notify;
});

</script>
