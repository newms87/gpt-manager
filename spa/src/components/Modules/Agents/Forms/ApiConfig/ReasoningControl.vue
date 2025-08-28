<template>
    <div class="reasoning-control">
        <div class="mb-3 flex items-center gap-2">
            <FaSolidLightbulb class="w-5 h-5 text-yellow-400" />
            <h3 class="text-lg font-semibold text-slate-200">Reasoning Configuration</h3>
            <QIcon name="info" class="w-4 h-4 text-slate-400 cursor-help">
                <QTooltip class="bg-slate-800 text-slate-200 shadow-xl max-w-lg">
                    <div class="p-3">
                        <div class="font-semibold mb-2 text-yellow-300">Advanced Reasoning Controls</div>
                        <p class="text-sm mb-3">Configure how the model approaches complex problems:</p>
                        <div class="space-y-2 text-xs">
                            <div><strong class="text-green-300">Effort Level:</strong></div>
                            <ul class="ml-4 space-y-1">
                                <li><strong>Low:</strong> Quick responses, basic reasoning</li>
                                <li><strong>Medium:</strong> Balanced thinking and speed</li>
                                <li><strong>High:</strong> Deep analysis, slower but thorough</li>
                            </ul>
                            <div class="mt-2"><strong class="text-blue-300">Summary Style:</strong></div>
                            <ul class="ml-4 space-y-1">
                                <li><strong>Auto:</strong> System decides best format</li>
                                <li><strong>Detailed:</strong> Full reasoning explanation</li>
                                <li><strong>None:</strong> No reasoning summary provided</li>
                            </ul>
                        </div>
                    </div>
                </QTooltip>
            </QIcon>
        </div>

        <div class="p-4 bg-slate-800/50 rounded-lg border border-slate-700">
            <div class="grid grid-cols-2 gap-4">
                <!-- Reasoning Effort -->
                <div class="space-y-2">
                    <label class="text-sm text-slate-300 font-medium">Effort Level</label>
                    <div class="grid grid-cols-3 gap-2">
                        <div
                            v-for="effort in effortLevels"
                            :key="effort"
                            :class="[
								'p-2 rounded text-center text-sm cursor-pointer transition-all',
								model.effort === effort
									? 'bg-yellow-600 text-white shadow-lg'
									: 'bg-slate-700 text-slate-300 hover:bg-slate-600'
							]"
                            @click="updateReasoning('effort', effort)"
                        >
                            {{ effort.charAt(0).toUpperCase() + effort.slice(1) }}
                        </div>
                    </div>
                </div>

                <!-- Reasoning Summary -->
                <div class="space-y-2">
                    <label class="text-sm text-slate-300 font-medium">Summary Style</label>
                    <SelectField
                        :model-value="model.summary === undefined ? null : model.summary"
                        :options="summaryOptions"
                        placeholder="None"
                        class="bg-slate-700"
                        @update:model-value="updateReasoning('summary', $event)"
                    />
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { FaSolidLightbulb } from "danx-icon";
import { QIcon, QTooltip } from "quasar";
import { SelectField } from "quasar-ui-danx";

interface ReasoningOptions {
    effort?: string;
    summary?: string;
}

const model = defineModel<ReasoningOptions>({ required: true });

const effortLevels = ["low", "medium", "high"] as const;

const summaryOptions = [
    { label: "Auto", value: "auto" },
    { label: "Detailed", value: "detailed" }
];

function updateReasoning(key: keyof ReasoningOptions, value: any) {
    model.value = {
        ...model.value,
        [key]: value
    };
}
</script>
