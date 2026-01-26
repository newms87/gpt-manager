<template>
    <!-- Green source handle dot for connecting to child nodes -->
    <Handle
        :id="position === 'right' ? 'source-right' : 'source-bottom'"
        type="source"
        :position="position === 'right' ? Position.Right : Position.Bottom"
        :class="[
            '!bg-green-500 !w-2.5 !h-2.5 !border-green-300 !rounded-full',
            { '!opacity-0': !visible }
        ]"
    />
</template>

<script setup lang="ts">
import { Handle, Position } from "@vue-flow/core";

defineProps<{
    position: "right" | "bottom";
    visible: boolean;
}>();
</script>
