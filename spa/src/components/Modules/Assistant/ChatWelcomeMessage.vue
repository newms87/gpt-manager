<template>
    <div class="welcome-message text-center py-8">
        <div class="bg-gradient-to-br from-blue-50 to-indigo-100 rounded-xl p-8 mx-auto max-w-lg shadow-lg border border-blue-200">
            <!-- Header -->
            <div class="flex items-center justify-center mb-6">
                <div class="flex items-center space-x-3">
                    <div class="bg-blue-500 p-3 rounded-full shadow-lg">
                        <FaSolidRobot class="w-6 h-6 text-white" />
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-gray-900">
                            {{ contextDisplayName }}
                        </h3>
                        <p class="text-sm text-blue-600 font-medium">
                            Ready to assist
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- Welcome Message -->
            <p class="text-gray-700 text-center mb-6 leading-relaxed">
                {{ welcomeMessage }}
            </p>
            
            <!-- Suggested Questions -->
            <div
                v-if="suggestedQuestions.length"
                class="space-y-3"
            >
                <div class="text-center">
                    <div class="inline-flex items-center space-x-2 bg-white/60 rounded-full px-4 py-2">
                        <FaSolidLightbulb class="w-4 h-4 text-amber-500" />
                        <span class="text-sm font-semibold text-gray-700">Quick Start Ideas</span>
                    </div>
                </div>
                
                <div class="grid gap-2">
                    <button
                        v-for="(question, index) in suggestedQuestions"
                        :key="question"
                        class="group relative bg-white hover:bg-blue-50 border border-blue-200 hover:border-blue-300 rounded-xl p-4 text-left transition-all duration-200 hover:shadow-md hover:scale-[1.02] transform"
                        @click="$emit('question-selected', question)"
                    >
                        <div class="flex items-center space-x-3">
                            <div class="flex-shrink-0 bg-gradient-to-r from-blue-500 to-indigo-500 text-white rounded-full w-6 h-6 flex items-center justify-center text-xs font-bold">
                                {{ index + 1 }}
                            </div>
                            <span class="text-sm font-medium text-gray-800 group-hover:text-blue-700">
                                {{ question }}
                            </span>
                        </div>
                        <div class="absolute inset-0 bg-gradient-to-r from-blue-500/0 to-indigo-500/0 group-hover:from-blue-500/5 group-hover:to-indigo-500/5 rounded-xl transition-all duration-200"></div>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { FaSolidRobot, FaSolidLightbulb } from "danx-icon";

interface Props {
    context: string;
    contextDisplayName: string;
    welcomeMessage: string;
    suggestedQuestions: string[];
}

defineProps<Props>();

interface Emits {
    (e: 'question-selected', question: string): void;
}

defineEmits<Emits>();
</script>