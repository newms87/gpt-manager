<template>
	<ThePrimaryLayout>
		<template #header>
			<ThePageHeader />
		</template>

		<template #sidebar>
			<CollapsableSidebar v-model:collapse="isCollapsed" min-width="4rem" name="primary-nav" class="primary-nav">
				<NavigationMenu
					:items="adminNavigation"
					:collapsed="isCollapsed"
					class="h-full"
					item-class="text-sky-700 hover:text-sky-100 hover:bg-sky-700"
				/>
			</CollapsableSidebar>
		</template>
	</ThePrimaryLayout>
</template>
<script setup lang="ts">
import ThePrimaryLayout from "@/components/Layouts/ThePrimaryLayout";
import ThePageHeader from "@/components/ThePageHeader";
import { adminNavigation } from "@/navigation";
import { CollapsableSidebar, NavigationMenu } from "quasar-ui-danx";
import { ref } from "vue";

const isCollapsed = ref(false);
</script>
