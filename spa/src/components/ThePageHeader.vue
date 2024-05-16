<template>
	<QToolbar>
		<QToolbarTitle class="bg-sky-800 py-2 flex items-center">
			<div class="pl-3 flex-grow">Sage Sweeper</div>
			<div class="px-3">
				<QBtn class="bg-sky-950 px-1 py-3 shadow-2" round>
					<AccountIcon class="w-4" />

					<QMenu>
						<a class="p-3 block hover:bg-slate-600 text-slate-300" @click="$router.push({ name: 'logout' })">Logout</a>
					</QMenu>
				</QBtn>
			</div>
		</QToolbarTitle>
	</QToolbar>
</template>
<script setup lang="ts">
import { FaSolidUser as AccountIcon } from "danx-icon";
import { QToolbar, QToolbarTitle } from "quasar";
</script>
