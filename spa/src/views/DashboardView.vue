<template>
    <div class="danx-styles">
        Dashboard
    </div>
</template>
<script setup>
import { FlashMessages } from 'quasar-ui-danx'
import { onMounted } from 'vue'

onMounted(() => {
    console.log('Dashboard view mounted')
    FlashMessages.success('WOOO', { timeout: 50000000 })
    FlashMessages.warning('WOOO 2', { timeout: 50000000 })
    FlashMessages.error('WOOO 3', { timeout: 50000000 })
})
</script>
