<template>
	<div id="primary-layout" class="min-w-xs h-full flex flex-col flex-nowrap bg-slate-900 text-slate-300">
		<slot name="header" />

		<div class="flex items-stretch flex-nowrap flex-grow overflow-hidden">
			<slot name="sidebar" />
			<Transition
				mode="out-in"
				:duration="300"
			>
				<main :key="$route.name?.split('.')[0]" class="flex-grow overflow-hidden">
					<slot>
						<RouterView />
					</slot>
				</main>
			</Transition>
		</div>

		<slot name="footer" />

		<ActionVnode />
	</div>
</template>
<script setup lang="ts">
import { usePusher } from "@/helpers/pusher";
import { useQuasar } from "quasar";
import { ActionVnode, FlashMessages } from "quasar-ui-danx";
import { onMounted } from "vue";

onMounted(() => {
	FlashMessages.notify = useQuasar().notify;
	usePusher();
});

</script>
