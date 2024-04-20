<template>
    <QToolbar>
        <QToolbarTitle class="bg-primary text-white py-2 bg-green-500">
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
import { QBtn, QToolbar, QToolbarTitle } from 'quasar'
import { useRoute } from 'vue-router'

const route = useRoute()
</script>
