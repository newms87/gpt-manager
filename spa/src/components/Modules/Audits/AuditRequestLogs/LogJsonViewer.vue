<template>
    <CodeViewer
        :model-value="json.parsed"
        format="yaml"
        collapsible
        default-collapsed
        class="log-json-viewer"
    />
</template>

<script setup lang="ts">
import { CodeViewer } from "quasar-ui-danx";
import type { EmbeddedJson } from "./useLogParser";

defineProps<{
    json: EmbeddedJson;
}>();
</script>

<style lang="scss" scoped>
.log-json-viewer {
    display: inline-flex;
    font-size: 0.85em;
    vertical-align: middle;
    width: 80%;

    :deep(.code-collapsed) {
        padding-right: 0;
        border-radius: 0.25rem;
    }

    :deep(.code-wrapper) {
        background: rgba(0, 0, 0, 0.2);
        border-radius: 0.25rem;
        max-height: 20rem;
    }

    :deep(.code-content) {
        padding: 0.5rem;
        margin: 0;
    }

    :deep(.code-footer) {
        padding: 0.25rem 0.5rem;
    }
}
</style>
