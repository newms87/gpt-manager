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
import { ActionVnode } from "quasar-ui-danx";
</script>
