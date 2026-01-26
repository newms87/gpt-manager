<template>
    <!-- Single blue dot indicating object type handle -->
    <div
        class="w-2.5 h-2.5 -ml-[.1rem] rounded-full bg-sky-400"
        :class="direction === 'LR' ? '-mt-[0.15rem]' : '-mt-0.5'"
    />
</template>

<script setup lang="ts">
defineProps<{
    direction: "LR" | "TB";
}>();
</script>
