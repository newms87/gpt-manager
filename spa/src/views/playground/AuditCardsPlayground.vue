<template>
  <div :class="theme === 'dark' ? 'bg-slate-900' : 'bg-slate-200'" class="p-6 min-h-screen">
    <!-- Theme Toggle -->
    <div class="flex items-center justify-between mb-6">
      <h2 :class="theme === 'dark' ? 'text-slate-300' : 'text-slate-700'" class="text-xl font-semibold">
        Audit Card Components
      </h2>
      <div class="flex items-center gap-3">
        <span :class="theme === 'dark' ? 'text-slate-400' : 'text-slate-600'" class="text-sm">
          Theme: {{ theme }}
        </span>
        <ActionButton
          :type="theme === 'dark' ? 'view' : 'hide'"
          :label="theme === 'dark' ? 'Switch to Light' : 'Switch to Dark'"
          :color="theme === 'dark' ? 'sky' : 'slate'"
          size="sm"
          @click="toggleTheme"
        />
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="isLoading" class="space-y-6">
      <QSkeleton class="h-48 rounded-lg" />
      <QSkeleton class="h-64 rounded-lg" />
      <QSkeleton class="h-48 rounded-lg" />
    </div>

    <!-- Content -->
    <div v-else class="space-y-8">
      <!-- Job Dispatch Card Section -->
      <section>
        <h3 :class="theme === 'dark' ? 'text-slate-400' : 'text-slate-600'" class="text-lg font-semibold mb-4">
          Job Dispatch Card
        </h3>
        <JobDispatchCard v-if="job" :job="job" />
        <div v-else :class="theme === 'dark' ? 'text-red-400' : 'text-red-600'" class="p-4 rounded bg-red-900/20">
          Failed to load job dispatch data
        </div>
      </section>

      <!-- API Log Entry Card Section -->
      <section>
        <h3 :class="theme === 'dark' ? 'text-slate-400' : 'text-slate-600'" class="text-lg font-semibold mb-4">
          API Log Entry Card
        </h3>
        <ApiLogEntryCard :api-log="apiLog" />
      </section>

      <!-- Error Log Entry Card Section -->
      <section>
        <h3 :class="theme === 'dark' ? 'text-slate-400' : 'text-slate-600'" class="text-lg font-semibold mb-4">
          Error Log Entry Card
        </h3>
        <ErrorLogEntryCard :error="errorLog" />
      </section>
    </div>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from "vue";
import { ActionButton } from "quasar-ui-danx";
import JobDispatchCard from "@/components/Modules/Audits/JobDispatches/JobDispatchCard.vue";
import ApiLogEntryCard from "@/components/Modules/Audits/ApiLogs/ApiLogEntryCard";
import ErrorLogEntryCard from "@/components/Modules/Audits/ErrorLogs/ErrorLogEntryCard";
import { jobDispatchRoutes } from "@/components/Modules/Audits/JobDispatches/jobDispatchRoutes";
import { provideAuditCardTheme, type AuditCardTheme } from "@/composables/useAuditCardTheme";
import type { JobDispatch, ApiLog, ErrorLogEntry } from "@/components/Modules/Audits/audit-requests";

// Theme management
const theme = ref<AuditCardTheme>("dark");
provideAuditCardTheme(theme);

function toggleTheme() {
  theme.value = theme.value === "dark" ? "light" : "dark";
}

// Data loading
const isLoading = ref(true);
const job = ref<JobDispatch | null>(null);

// Mock data for API Log
const mockApiLog: ApiLog = {
  id: "1",
  api_class: "OpenAI",
  service_name: "gpt-5",
  status_code: 200,
  method: "POST",
  url: "https://api.openai.com/v1/responses",
  request: '{"model": "gpt-5", "messages": [{"role": "user", "content": "Hello"}]}',
  response: '{"id": "resp_123", "choices": [{"message": {"content": "Hi there!"}}]}',
  request_headers: { "Authorization": "Bearer sk-***", "Content-Type": "application/json" },
  response_headers: {},
  run_time_ms: 1523,
  started_at: "2025-01-13T10:00:00Z",
  finished_at: "2025-01-13T10:00:01.523Z",
  created_at: "2025-01-13T10:00:00Z"
};

// Mock data for Error Log
const mockError: ErrorLogEntry = {
  id: "1",
  audit_request_id: null,
  error_class: "RuntimeException",
  code: "500",
  level: 400,
  last_seen_at: "2025-01-13T10:00:00Z",
  file: "/app/Services/Example/ExampleService.php",
  line: "42",
  message: "An example error message for demonstration purposes.",
  data: "{}",
  stack_trace: [
    { file: "/app/Services/Example/ExampleService.php", line: "42", function: "process", class: "App\\Services\\Example\\ExampleService", type: "->" },
    { file: "/app/Http/Controllers/ExampleController.php", line: "28", function: "handle", class: "App\\Http\\Controllers\\ExampleController", type: "->" }
  ],
  created_at: "2025-01-13T10:00:00Z"
};

// Use real API log from job if available, otherwise use mock
const apiLog = ref<ApiLog>(mockApiLog);
const errorLog = ref<ErrorLogEntry>(mockError);

onMounted(async () => {
  try {
    const result = await jobDispatchRoutes.details({ id: 1235 });
    if (result) {
      job.value = result;
      // If job has API logs, use the first one
      if (job.value?.apiLogs?.length) {
        apiLog.value = job.value.apiLogs[0];
      }
      // If job has errors, use the first one
      if (job.value?.errors?.length) {
        errorLog.value = job.value.errors[0];
      }
    }
  } catch (e) {
    console.error("Failed to load job dispatch:", e);
  } finally {
    isLoading.value = false;
  }
});
</script>
