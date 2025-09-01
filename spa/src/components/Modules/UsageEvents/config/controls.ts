import { UsageEvent } from "@/types";
import { ListController, useControls } from "quasar-ui-danx";
import { ref } from "vue";
import { routes } from "./routes";

const showMetadataModal = ref(false);
const selectedEventForMetadata = ref<UsageEvent | null>(null);

export const controls = {
    ...useControls("usage-events", {
        label: "Usage Events",
        routes
    }),
    
    showMetadataModal,
    selectedEventForMetadata,
    
    showMetadata(event: UsageEvent) {
        selectedEventForMetadata.value = event;
        showMetadataModal.value = true;
    },
    
    hideMetadata() {
        showMetadataModal.value = false;
        selectedEventForMetadata.value = null;
    }
} as ListController<UsageEvent> & {
    showMetadataModal: typeof showMetadataModal;
    selectedEventForMetadata: typeof selectedEventForMetadata;
    showMetadata: (event: UsageEvent) => void;
    hideMetadata: () => void;
};