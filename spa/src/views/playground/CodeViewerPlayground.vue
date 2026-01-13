<template>
  <div>
    <h2 class="text-xl font-semibold mb-4 text-slate-300">CodeViewer - Syntax Highlighting</h2>
    <div class="grid grid-cols-3 gap-6">
      <!-- CSS CodeViewer -->
      <div class="flex flex-col">
        <h3 class="text-md font-semibold mb-3 text-slate-400">CSS</h3>
        <div class="bg-slate-800 rounded-lg border border-slate-600 overflow-hidden">
          <CodeViewer
            v-model="cssContent"
            format="css"
            can-edit
            show-version
            editor-class="min-h-[300px]"
          />
        </div>
      </div>

      <!-- JavaScript CodeViewer -->
      <div class="flex flex-col">
        <h3 class="text-md font-semibold mb-3 text-slate-400">JavaScript</h3>
        <div class="bg-slate-800 rounded-lg border border-slate-600 overflow-hidden">
          <CodeViewer
            v-model="jsContent"
            format="javascript"
            can-edit
            show-version
            editor-class="min-h-[300px]"
          />
        </div>
      </div>

      <!-- HTML CodeViewer -->
      <div class="flex flex-col">
        <h3 class="text-md font-semibold mb-3 text-slate-400">HTML</h3>
        <div class="bg-slate-800 rounded-lg border border-slate-600 overflow-hidden">
          <CodeViewer
            v-model="htmlContent"
            format="html"
            can-edit
            show-version
            editor-class="min-h-[300px]"
          />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from "vue";
import { CodeViewer } from "quasar-ui-danx";

const cssContent = ref(`.container {
  display: flex;
  flex-direction: column;
  padding: 1rem;
  background-color: #f5f5f5;
}

/* Button styles with hover state */
.button {
  background-color: #3498db;
  color: white;
  border-radius: 4px;
  padding: 0.5rem 1rem;
}

.button:hover {
  background-color: #2980b9;
}

@media (max-width: 768px) {
  .container {
    padding: 0.5rem;
  }
}`);

const jsContent = ref(`// JavaScript example
const greeting = "Hello, World!";
let count = 42;

function fibonacci(n) {
  if (n <= 1) return n;
  return fibonacci(n - 1) + fibonacci(n - 2);
}

/* Multi-line comment */
const double = (x) => x * 2;

const obj = {
  name: "test",
  value: true,
  items: [1, 2, 3]
};

// Regex example
const pattern = /[a-z]+/gi;

async function fetchData() {
  const response = await fetch("/api/data");
  return response.json();
}`);

const htmlContent = ref(`<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Test Page</title>
  <style>
    body {
      font-family: sans-serif;
      margin: 0;
      padding: 20px;
    }
    .highlight {
      color: #e74c3c;
    }
  </style>
</head>
<body>
  <!-- Main content -->
  <div class="container" id="app">
    <h1>Welcome</h1>
    <p class="highlight">This is a test.</p>
  </div>
  <script>
    const app = document.getElementById("app");
    console.log("App initialized:", app);

    function greet(name) {
      return "Hello, " + name + "!";
    }
  <\/script>
</body>
</html>`);
</script>
