import { onScopeDispose } from "vue";

// Minimal, typed event bus powered by EventTarget
const bus = new EventTarget();

function busEmit<E extends EventKey>(type: E, payload: EventMap[E]) {
    bus.dispatchEvent(new CustomEvent(String(type), { detail: payload }));
}

function busOn<E extends EventKey>(type: E, handler: Handler<E>) {
    const listener = (e: Event) => handler((e as CustomEvent).detail);
    bus.addEventListener(String(type), listener);
    // return an off() function
    return () => bus.removeEventListener(String(type), listener);
}

export function useEventBus() {
    // Auto-cleaning listener for any composable scope
    function busListen<T extends Parameters<typeof busOn>[0]>(
        type: T,
        handler: Parameters<typeof busOn<T>>[1]
    ) {
        const off = busOn(type, handler as any);
        onScopeDispose(off);
        return off; // in case you want to manually stop earlier
    }

    return {
        busEmit,
        busListen       // auto-disposes on scope end
    };
}
