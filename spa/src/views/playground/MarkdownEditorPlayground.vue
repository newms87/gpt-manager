<template>
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
</template>

<script setup lang="ts">
import { ref } from "vue";
import { MarkdownEditor } from "quasar-ui-danx";

const markdownContent = ref(`# Markdown Editor Demo

Welcome to the **WYSIWYG Markdown Editor**! This editor supports *hotkey-driven* formatting with real-time two-way sync.

## Text Formatting

Make text **bold** with \`Ctrl+B\`, *italic* with \`Ctrl+I\`, or ~~strikethrough~~ with \`Ctrl+Shift+S\`.

Use \`Ctrl+E\` for \`inline code\` like variable names or commands.

Use \`Ctrl+Shift+H\` for ==highlighted text== and \`Ctrl+U\` for <u>underlined text</u>.

## Links

Create links with \`Ctrl+K\`. Try it: [Example Link](https://example.com)

## Headings

Use \`Ctrl+1\` through \`Ctrl+6\` to set heading levels, or type \`# \` at the start of a line.

### This is H3
#### This is H4
##### This is H5
###### This is H6

## Blockquotes

Create blockquotes with \`Ctrl+Shift+Q\`:

> This is a blockquote. It's great for highlighting important information or quoting text from other sources.
>
> You can have multiple paragraphs in a blockquote.

## Lists

### Bullet List

Create with \`Ctrl+Shift+[\` or type \`- \` at line start:

- First item
- Second item
  - Nested item (press Tab to indent)
  - Another nested item
    - Even deeper nesting
- Third item

### Numbered List

Create with \`Ctrl+Shift+]\` or type \`1. \` at line start:

1. Step one
2. Step two
   1. Sub-step A
   2. Sub-step B
3. Step three

## Code Blocks

Type \\\`\\\`\\\`yaml and press Enter, or use \`Ctrl+Shift+K\`:

\`\`\`yaml
name: Markdown Editor
version: 1.0.0
features:
  - headings
  - formatting
  - lists
  - code_blocks
settings:
  theme: dark
  autosave: true
\`\`\`

Use \`Ctrl+Alt+L\` to cycle languages, or \`Ctrl+Alt+Shift+L\` to search all languages.

---

## Two-Way Sync

Edit in either panel:
- Changes in the **raw markdown textarea** appear in the WYSIWYG editor
- Changes in the **WYSIWYG editor** appear in the textarea

Try editing this content to see all features in action!
`);
</script>
