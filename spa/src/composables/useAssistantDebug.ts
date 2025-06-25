import { ref } from 'vue';

// Global debug state - can be toggled via browser console or settings
// Enable automatically in development mode
const isDebugEnabled = ref(import.meta.env.DEV || false);

// Enable/disable debug mode globally
function enableDebug() {
    isDebugEnabled.value = true;
    console.log('🔧 Assistant Debug Mode: ENABLED');
}

function disableDebug() {
    isDebugEnabled.value = false;
    console.log('🔧 Assistant Debug Mode: DISABLED');
}

// Debug categories for different types of logging
const DebugCategory = {
    MESSAGE: '💬',
    WEBSOCKET: '📡',
    THREAD: '🧵',
    STORAGE: '💾',
    API: '🌐',
    UI: '🎨',
    ERROR: '❌',
    SUCCESS: '✅',
    INFO: 'ℹ️',
    NETWORK: '🔌'
} as const;

// Centralized debug logging function
function debugLog(category: keyof typeof DebugCategory, message: string, data?: any) {
    if (!isDebugEnabled.value) return;
    
    const icon = DebugCategory[category];
    const timestamp = new Date().toLocaleTimeString();
    
    if (data !== undefined) {
        console.log(`${icon} [${timestamp}] ${message}`, data);
    } else {
        console.log(`${icon} [${timestamp}] ${message}`);
    }
}

// Specific debug functions for common chat operations
function debugSendMessage(message: string) {
    debugLog('MESSAGE', `Sending message: "${message}"`);
}

function debugChatResponse(response: any) {
    debugLog('API', 'Chat response received', response);
}

function debugThreadStored(threadId: string | number) {
    debugLog('STORAGE', `Thread stored: ${threadId}`);
}

function debugWebSocketSubscribe(threadId: string | number) {
    debugLog('WEBSOCKET', `Subscribing to thread updates: ${threadId}`);
}

function debugWebSocketUpdate(threadId: string | number, thread: any) {
    debugLog('WEBSOCKET', `Received update for thread: ${threadId}`, thread);
}

function debugThreadLoaded(thread: any) {
    debugLog('THREAD', 'Stored thread loaded', thread);
}

function debugThreadCleared() {
    debugLog('THREAD', 'Thread cleared - starting new chat');
}

function debugStorageCheck(storedThreadId: string | null) {
    debugLog('STORAGE', `Checking for stored thread: ${storedThreadId || 'none'}`);
}

function debugError(operation: string, error: any) {
    debugLog('ERROR', `Failed ${operation}`, error);
}

// Make debug functions available globally for easy browser console access
if (typeof window !== 'undefined') {
    (window as any).assistantDebug = {
        enable: enableDebug,
        disable: disableDebug,
        isEnabled: () => isDebugEnabled.value
    };
    
    // Show startup message about debug mode
    if (isDebugEnabled.value) {
        console.log('🔧 Assistant Debug Mode: ENABLED (Development mode)');
        console.log('💡 Use assistantDebug.disable() to turn off debug logging');
    } else {
        console.log('🔧 Assistant Debug Mode: DISABLED');
        console.log('💡 Use assistantDebug.enable() to turn on debug logging');
    }
}

export function useAssistantDebug() {
    return {
        // State
        isDebugEnabled,
        
        // Controls
        enableDebug,
        disableDebug,
        
        // General logging
        debugLog,
        
        // Specific operations
        debugSendMessage,
        debugChatResponse,
        debugThreadStored,
        debugWebSocketSubscribe,
        debugWebSocketUpdate,
        debugThreadLoaded,
        debugThreadCleared,
        debugStorageCheck,
        debugError,
        
        // Categories for custom logging
        DebugCategory
    };
}