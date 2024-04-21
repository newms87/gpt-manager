<template>
    <QToolbar>
        <QToolbarTitle class="bg-sky-800 py-2">
            <QBtn
                flat
                round
                dense
                icon="menu"
            />
            <span class="ml-3">{{ route.meta.title }}</span>
        </QToolbarTitle>
    </QToolbar>
</template>
<script setup lang="ts">
import { QBtn, QToolbar, QToolbarTitle } from "quasar";
import { useRoute } from "vue-router";

const route = useRoute();
</script>
