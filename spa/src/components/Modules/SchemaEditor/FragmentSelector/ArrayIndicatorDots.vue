<template>
    <!-- Triple amber dots indicating array type handle -->
    <!-- LR layout: vertical arrangement -->
    <div v-if="direction === 'LR'" class="relative w-4 h-4 -mt-[.3rem] -ml-2">
        <div class="absolute top-0 right-0 w-1.5 h-1.5 rounded-full bg-amber-400" />
        <div class="absolute top-[.35rem] left-0 w-1.5 h-1.5 rounded-full bg-amber-400" />
        <div class="absolute bottom-0 right-0 w-1.5 h-1.5 rounded-full bg-amber-400" />
    </div>
    <!-- TB layout: horizontal arrangement -->
    <div v-else class="relative w-4 h-4 -mt-2 -ml-[.33rem]">
        <div class="absolute top-0 left-[50%] -ml-[.1875rem] w-1.5 h-1.5 rounded-full bg-amber-400" />
        <div class="absolute bottom-0 left-0 w-1.5 h-1.5 rounded-full bg-amber-400" />
        <div class="absolute bottom-0 right-0 w-1.5 h-1.5 rounded-full bg-amber-400" />
    </div>
</template>

<script setup lang="ts">
defineProps<{
    direction: "LR" | "TB";
}>();
</script>
