<template>
  <div class="p-6 h-full overflow-auto">
    <h1 class="text-2xl font-bold mb-6 text-slate-200">Component Playground</h1>

    <div class="grid grid-cols-2 gap-6">
      <!-- Raw Markdown Input -->
      <div class="flex flex-col">
        <h2 class="text-lg font-semibold mb-3 text-slate-300">Raw Markdown</h2>
        <textarea
          v-model="markdownContent"
          class="flex-1 min-h-[600px] p-4 bg-slate-800 text-slate-200 rounded-lg border border-slate-600 font-mono text-sm resize-none"
          placeholder="Enter markdown content here..."
        />
      </div>

      <!-- WYSIWYG Markdown Editor -->
      <div class="flex flex-col">
        <h2 class="text-lg font-semibold mb-3 text-slate-300">
          WYSIWYG Editor
          <span class="text-xs text-slate-500 ml-2">(Ctrl+\ for shortcuts)</span>
        </h2>
        <div class="flex-1 min-h-[600px] bg-slate-800 rounded-lg border border-slate-600 overflow-hidden">
          <MarkdownEditor
            v-model="markdownContent"
            placeholder="Type here... Use Ctrl+1-6 for headings"
            min-height="600px"
          />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from "vue";
import { MarkdownEditor } from "quasar-ui-danx";

const markdownContent = ref(`# Markdown Editor Test

This playground tests the new WYSIWYG Markdown Editor.

## Try These Features

### Keyboard Shortcuts
- **Ctrl+1** through **Ctrl+6**: Set heading level
- **Ctrl+0**: Convert to paragraph
- **Ctrl+>**: Increase heading level
- **Ctrl+<**: Decrease heading level
- **Ctrl+\\**: Show all shortcuts

### Character Sequences
Type these at the start of a line:
- \`# \` through \`###### \` for headings

## Edit in Either Panel

Changes in the **raw markdown textarea** will appear in the editor.
Changes in the **WYSIWYG editor** will appear in the textarea.

Try it out!
`);
</script>
