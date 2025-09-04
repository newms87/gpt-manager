<template>
    <div class="workflow-editor overflow-hidden relative">
        <VueFlow
            v-if="workflowDefinition"
            :id="instanceId"
            v-model="nodes"
            v-model:edges="edges"
            :default-viewport="{ zoom: 1 }"
            :min-zoom="0.2"
            :max-zoom="2"
            snap-to-grid
            fit-view-on-init
            :snap-grid="[20, 20]"
            class="workflow-canvas"
            :connect-on-click="false"
            elevate-edges-on-select
            :connection-mode="readonly ? 'Loose' : ConnectionMode.Strict"
            :nodes-draggable="!readonly"
            :edges-updatable="!readonly"
            :nodes-connectable="true"
            @pane-ready="onPaneReady"
            @connect="onConnectionAdd"
            @node-drag-stop="onSelectionDragStop"
            @selection-drag-stop="onSelectionDragStop"
            @dragover="onDragOver"
            @drop="e => handleExternalDrop(isntanceId, e)"
        >
            <template #node-custom="nodeProps">
                <WorkflowCanvasNode
                    :node="nodeProps"
                    :instance-id="instanceId"
                    :workflow-definition="workflowDefinition"
                    :workflow-run="workflowRun"
                    :loading="loading"
                    :readonly="readonly"
                    @copy="node => $emit('node-copy', resolveWorkflowNode(node))"
                    @edit="node => $emit('node-edit', resolveWorkflowNode(node))"
                    @remove="node => $emit('node-remove', resolveWorkflowNode(node))"
                />
            </template>
            <template #edge-custom="edgeProps">
                <WorkflowCanvasEdge
                    :edge="edgeProps"
                    :nodes="nodes"
                    :workflow-run="workflowRun"
                    :readonly="readonly"
                    @remove="onConnectionRemove"
                />
            </template>
            <template #connection-line="connectionLineProps">
                <WorkflowCanvasConnectionLine v-bind="connectionLineProps" />
            </template>

            <Background variant="dots" />
        </VueFlow>
    </div>
</template>

<script setup lang="ts">
import { handleExternalDrop, onDragOver } from "@/components/Modules/WorkflowCanvas/dragNDrop";
import {
    connectWorkflowNodes,
    convertConnectionsToVueFlow,
    convertNodesToVueFlow
} from "@/components/Modules/WorkflowCanvas/helpers";
import WorkflowCanvasConnectionLine from "@/components/Modules/WorkflowCanvas/WorkflowCanvasConnectionLine";
import WorkflowCanvasEdge from "@/components/Modules/WorkflowCanvas/WorkflowCanvasEdge";
import { dxWorkflowRun } from "@/components/Modules/WorkflowDefinitions/WorkflowRuns/config";
import { useEventBus } from "@/helpers";
import { WorkflowConnection, WorkflowDefinition, WorkflowNode, WorkflowRun } from "@/types";
import { Background } from "@vue-flow/background";
import { Connection, ConnectionMode, Edge, EdgeProps, Node, useVueFlow, VueFlow } from "@vue-flow/core";
import "@vue-flow/core/dist/style.css";
import "@vue-flow/core/dist/theme-default.css";
import { onMounted, ref, watch } from "vue";
import WorkflowCanvasNode from "./WorkflowCanvasNode.vue";

const emit = defineEmits<{
    (e: "node-click", node: WorkflowNode): void;
    (e: "node-position", node: WorkflowNode, position: { x: number, y: number }): void;
    (e: "node-copy", node: WorkflowNode): void;
    (e: "node-edit", node: WorkflowNode): void;
    (e: "node-remove", node: WorkflowNode): void;
    (e: "connection-add", connection: WorkflowConnection): void;
    (e: "connection-remove", connection: WorkflowConnection): void;
}>();

const props = withDefaults(defineProps<{
    workflowRun?: WorkflowRun;
    loading?: boolean;
    readonly?: boolean;
    instanceId?: string;
}>(), {
    workflowRun: null,
    instanceId: "workflow-canvas-vf"
});

let vueFlowInstance = null;
const workflowDefinition = defineModel<WorkflowDefinition>();

// Reference to internal Vue Flow nodes
const nodes = ref<Node[]>([]);
const edges = ref<Edge[]>([]);

const previousWorkflowDefinitionId = ref<number | null>(null);

const { busEmit } = useEventBus();
const { onViewportChangeEnd } = useVueFlow(props.instanceId);
// Always keep the player size in sync with the canvas / window size so the animation appears full size and sharp
onViewportChangeEnd(() => {
    busEmit("workflow-canvas:viewport-change");
});

function convertToVueFlow() {
    if (workflowDefinition.value?.nodes) {
        nodes.value = convertNodesToVueFlow(workflowDefinition.value.nodes);
        edges.value = convertConnectionsToVueFlow(workflowDefinition.value.connections || []);
    }

    // Correct the viewport when the workflowDefinition has changed (after it is finished loading - ie w/ nodes)
    if (previousWorkflowDefinitionId.value !== workflowDefinition.value?.id && workflowDefinition.value?.nodes?.length > 0) {
        previousWorkflowDefinitionId.value = workflowDefinition.value?.id as number;
    }
}

// Watch for changes in the prop model and update the flow
watch(() => workflowDefinition.value, convertToVueFlow, { deep: true });
watch(() => props.workflowRun, loadWorkflowRunDetails);
onMounted(() => {
    convertToVueFlow();
    loadWorkflowRunDetails();
});

function loadWorkflowRunDetails() {
    if (props.workflowRun) dxWorkflowRun.routes.details(props.workflowRun);
}

/*********** Node Related Methods *********/
function resolveWorkflowNode(node: Node) {
    const workflowNode = workflowDefinition.value.nodes.find(n => n.id == +node.id);

    if (!workflowNode) {
        throw new Error("Workflow node not found: " + node.id);
    }

    return workflowNode;
}

function onSelectionDragStop(selection) {
    if (props.readonly) {
        return;
    }
    for (const node of selection.nodes) {
        emit("node-position", resolveWorkflowNode(node), { ...node.position });
    }
}

/*********** Connection Related Methods *********/
function resolveWorkflowConnection(edge: EdgeProps) {
    const workflowNode = workflowDefinition.value.connections.find(c => c.id == +edge.id);

    if (!workflowNode) {
        throw new Error("Workflow node not found: " + edge.id);
    }

    return workflowNode;
}

function onConnectionAdd(connection: Connection) {
    // Prevent connection creation in readonly mode
    if (props.readonly) {
        return;
    }

    const connections = connectWorkflowNodes(workflowDefinition.value.connections, connection);
    if (connections) {
        emit("connection-add", connections.pop());
    }
}

function onConnectionRemove(edge: EdgeProps) {
    if (props.readonly) {
        return;
    }

    emit("connection-remove", resolveWorkflowConnection(edge));
}

function onPaneReady(vfi) {
    vueFlowInstance = vfi;
    vueFlowInstance.fitView();
}
</script>
