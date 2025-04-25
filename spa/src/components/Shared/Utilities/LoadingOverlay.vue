<template>
	<div class="absolute-top-left w-full h-full flex items-center justify-center z-10">
		<div class="absolute-top-left w-full h-full bg-slate-950 opacity-80" />
		<LoadingSandLottie autoplay class="relative z-10" />
	</div>
</template>
<script setup lang="ts">
import LoadingSandLottie from "@/assets/dotlottie/LoadingSandLottie";
</script>
