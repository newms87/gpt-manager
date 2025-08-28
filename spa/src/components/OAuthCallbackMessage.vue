<template>
  <Transition
    name="slide-down"
    enter-active-class="transition-all duration-300 ease-out"
    enter-from-class="transform -translate-y-full opacity-0"
    enter-to-class="transform translate-y-0 opacity-100"
    leave-active-class="transition-all duration-200 ease-in"
    leave-from-class="transform translate-y-0 opacity-100"
    leave-to-class="transform -translate-y-full opacity-0"
  >
    <div
      v-if="showMessage"
      :class="messageClasses"
      class="fixed top-0 left-0 right-0 z-50 mx-auto max-w-4xl px-4 py-3 border-l-4 shadow-lg"
    >
      <div class="flex items-center justify-between">
        <div class="flex items-center space-x-3">
          <component
            :is="messageIcon"
            class="w-5 h-5 flex-shrink-0"
          />
          <div class="font-medium">
            {{ messageTitle }}
          </div>
          <div
            v-if="messageText"
            class="text-sm opacity-90"
          >
            {{ messageText }}
          </div>
          <div
            v-if="serviceName"
            class="text-xs px-2 py-1 rounded-full bg-black bg-opacity-10 font-medium"
          >
            {{ serviceName }}
          </div>
        </div>
        <button
          @click="dismissMessage"
          class="ml-4 p-1 rounded-full hover:bg-black hover:bg-opacity-10 transition-colors"
          type="button"
        >
          <CloseIcon class="w-4 h-4" />
        </button>
      </div>
    </div>
  </Transition>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import { useRouter } from "vue-router";
import {
  FaSolidCheck as CheckIcon,
  FaSolidTriangleExclamation as WarningIcon,
  FaSolidX as CloseIcon
} from "danx-icon";

// Router for query parameter handling
const router = useRouter();

// State
const showMessage = ref(false);
const isSuccess = ref(false);
const isError = ref(false);
const messageText = ref("");
const serviceName = ref("");

// Computed properties
const messageIcon = computed(() => {
  return isSuccess.value ? CheckIcon : WarningIcon;
});

const messageTitle = computed(() => {
  if (isSuccess.value) {
    return serviceName.value 
      ? `${serviceName.value} Connected Successfully`
      : "OAuth Connection Successful";
  }
  return serviceName.value
    ? `${serviceName.value} Connection Failed`
    : "OAuth Connection Failed";
});

const messageClasses = computed(() => {
  if (isSuccess.value) {
    return "bg-green-50 border-green-200 text-green-800";
  }
  return "bg-red-50 border-red-200 text-red-800";
});

// Methods
function dismissMessage() {
  showMessage.value = false;
}

function setupAutoHide() {
  if (isSuccess.value) {
    // Auto-dismiss success messages after 5 seconds
    setTimeout(() => {
      if (showMessage.value) {
        dismissMessage();
      }
    }, 5000);
  } else if (isError.value) {
    // Auto-dismiss error messages after 10 seconds
    setTimeout(() => {
      if (showMessage.value) {
        dismissMessage();
      }
    }, 10000);
  }
}

function processOAuthCallback() {
  const currentRoute = router.currentRoute.value;
  const query = currentRoute.query;
  

  // Check for OAuth callback parameters
  const hasOAuthSuccess = query.oauth_success === "true";
  const hasOAuthError = query.oauth_error === "true";

  if (!hasOAuthSuccess && !hasOAuthError) {
    return; // No OAuth callback to process
  }
  

  // Extract callback data
  isSuccess.value = hasOAuthSuccess;
  isError.value = hasOAuthError;
  messageText.value = (query.message as string) || "";
  serviceName.value = (query.service as string) || "";

  // Show the message
  showMessage.value = true;

  // Setup auto-hide timer
  setupAutoHide();

  // Clean up URL parameters
  const cleanQuery = { ...query };
  delete cleanQuery.oauth_success;
  delete cleanQuery.oauth_error;
  delete cleanQuery.message;
  delete cleanQuery.service;

  // Replace current route to clean URL without triggering navigation
  router.replace({
    path: currentRoute.path,
    query: Object.keys(cleanQuery).length > 0 ? cleanQuery : undefined
  });
}

// Initialize on component mount
onMounted(() => {
  processOAuthCallback();
});
</script>

<style lang="scss" scoped>
// Additional styles if needed for complex animations
</style>