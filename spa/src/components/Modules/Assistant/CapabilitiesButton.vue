<template>
    <div class="capabilities-button">
        <!-- Capabilities Button -->
        <button
            @click="handleButtonClick"
            class="bg-blue-500 hover:bg-blue-400 text-white rounded-full p-1.5 transition-all duration-200 hover:scale-110"
            title="View my capabilities"
        >
            <FaSolidGear class="w-3 h-3" />
        </button>
        
        <!-- Capabilities Popup Menu -->
        <div
            v-if="showCapabilitiesMenu && contextCapabilities.length"
            class="fixed inset-0 z-50 flex items-center justify-center p-4"
            @click="showCapabilitiesMenu = false"
        >
            <div class="absolute inset-0 bg-black/20 backdrop-blur-sm"></div>
            <div
                class="relative bg-white rounded-2xl shadow-2xl border border-gray-200 max-w-md w-full max-h-[80vh] overflow-hidden"
                @click.stop
            >
                <!-- Header -->
                <div class="bg-gradient-to-r from-blue-500 to-indigo-600 p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-3">
                            <div class="bg-white/20 p-2 rounded-lg">
                                <FaSolidGear class="w-5 h-5" />
                            </div>
                            <div>
                                <h4 class="font-bold text-lg">My Capabilities</h4>
                                <p class="text-blue-100 text-sm">What I can help you with</p>
                            </div>
                        </div>
                        <button
                            @click="showCapabilitiesMenu = false"
                            class="bg-white/20 hover:bg-white/30 rounded-lg p-2 transition-colors"
                        >
                            <FaSolidX class="w-4 h-4" />
                        </button>
                    </div>
                </div>
                
                <!-- Capabilities List -->
                <div class="p-6 max-h-96 overflow-y-auto">
                    <div class="space-y-3">
                        <div
                            v-for="(capability, index) in contextCapabilities"
                            :key="capability.key"
                            class="flex items-start space-x-3 p-3 bg-gray-50 hover:bg-blue-50 rounded-lg transition-colors group"
                        >
                            <div class="flex-shrink-0 bg-gradient-to-r from-blue-500 to-indigo-500 text-white rounded-full w-6 h-6 flex items-center justify-center text-xs font-bold mt-0.5">
                                {{ index + 1 }}
                            </div>
                            <div class="flex-1">
                                <p class="text-sm font-medium text-gray-800 group-hover:text-blue-700">
                                    {{ capability.label }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Footer -->
                <div class="bg-gray-50 p-4 border-t border-gray-200">
                    <p class="text-xs text-gray-500 text-center">
                        Click on a suggested question above to get started!
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, computed } from "vue";
import { FaSolidGear, FaSolidX } from "danx-icon";
import { useAssistantGlobalContext } from "@/composables/useAssistantGlobalContext";
import { useAssistantDebug } from "@/composables/useAssistantDebug";

// Get capabilities directly from the global context
const { currentContext } = useAssistantGlobalContext();

// State
const showCapabilitiesMenu = ref(false);
const { debugError } = useAssistantDebug();
const contextCapabilities = ref<Array<{ key: string; label: string }>>([]);
const capabilitiesLoaded = ref(false);

// Load capabilities from API (only when needed)
async function loadCapabilities() {
    try {
        if (!currentContext.value || capabilitiesLoaded.value) return;
        
        const { request } = await import("quasar-ui-danx");
        const params = new URLSearchParams();
        params.append('context', currentContext.value);
        
        const url = `assistant/capabilities?${params.toString()}`;
        const capabilities = await request.get(url);
        
        if (capabilities) {
            contextCapabilities.value = Object.entries(capabilities).map(([key, label]) => ({
                key,
                label: label as string,
            }));
            capabilitiesLoaded.value = true;
        }
    } catch (error) {
        debugError('loading capabilities', error);
    }
}

// Reset capabilities when context changes
import { watch } from "vue";
watch(currentContext, () => {
    capabilitiesLoaded.value = false;
    contextCapabilities.value = [];
});

// Load capabilities when button is clicked
function handleButtonClick() {
    if (!capabilitiesLoaded.value) {
        loadCapabilities();
    }
    showCapabilitiesMenu.value = !showCapabilitiesMenu.value;
}
</script>