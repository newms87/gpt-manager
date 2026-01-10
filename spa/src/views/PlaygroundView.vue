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

    <!-- CodeViewer Component Section -->
    <div class="mt-8">
      <h2 class="text-xl font-semibold mb-4 text-slate-300">CodeViewer Component</h2>
      <div class="grid grid-cols-2 gap-6">
        <!-- YAML CodeViewer -->
        <div class="flex flex-col">
          <h3 class="text-md font-semibold mb-3 text-slate-400">YAML Format (Editable)</h3>
          <div class="bg-slate-800 rounded-lg border border-slate-600 overflow-hidden">
            <CodeViewer
              v-model="yamlContent"
              format="yaml"
              can-edit
              editor-class="min-h-[300px]"
            />
          </div>
        </div>

        <!-- JSON CodeViewer -->
        <div class="flex flex-col">
          <h3 class="text-md font-semibold mb-3 text-slate-400">JSON Format (Editable)</h3>
          <div class="bg-slate-800 rounded-lg border border-slate-600 overflow-hidden">
            <CodeViewer
              v-model="jsonContent"
              format="json"
              can-edit
              editor-class="min-h-[300px]"
            />
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from "vue";
import { CodeViewer, MarkdownEditor } from "quasar-ui-danx";

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

## Tables

Create tables with \`Ctrl+Alt+Shift+T\`. Navigate with Tab/Shift+Tab.

| Feature | Hotkey | Description |
|---------|--------|-------------|
| Insert table | Ctrl+Alt+Shift+T | Opens dimension selector |
| Next cell | Tab | Move to next cell |
| Previous cell | Shift+Tab | Move to previous cell |
| Row above | Ctrl+Alt+Shift+Up | Insert row above |
| Row below | Ctrl+Alt+Shift+Down | Insert row below |
| Column left | Ctrl+Alt+Shift+Left | Insert column left |
| Column right | Ctrl+Alt+Shift+Right | Insert column right |
| Delete row | Ctrl+Alt+Backspace | Delete current row |
| Delete column | Ctrl+Shift+Backspace | Delete current column |
| Delete table | Ctrl+Alt+Shift+Backspace | Delete entire table |
| Cycle alignment | Ctrl+Alt+L | Left -> Center -> Right |

---

## Horizontal Rules

Insert a horizontal rule with \`Ctrl+Shift+Enter\` (that's a horizontal line above this section).

---

## Two-Way Sync

Edit in either panel:
- Changes in the **raw markdown textarea** appear in the WYSIWYG editor
- Changes in the **WYSIWYG editor** appear in the textarea

## Keyboard Shortcuts Reference

| Action | Shortcut |
|--------|----------|
| Bold | Ctrl+B |
| Italic | Ctrl+I |
| Underline | Ctrl+U |
| Strikethrough | Ctrl+Shift+S |
| Highlight | Ctrl+Shift+H |
| Inline Code | Ctrl+E |
| Link | Ctrl+K |
| Heading 1-6 | Ctrl+1-6 |
| Paragraph | Ctrl+0 |
| Blockquote | Ctrl+Shift+Q |
| Bullet List | Ctrl+Shift+[ |
| Numbered List | Ctrl+Shift+] |
| Code Block | Ctrl+Shift+K |
| Exit Code Block | Ctrl+Enter |
| Insert Table | Ctrl+Alt+Shift+T |
| Horizontal Rule | Ctrl+Shift+Enter |
| Language Cycle | Ctrl+Alt+L |
| Language Search | Ctrl+Alt+Shift+L |
| Indent | Tab |
| Outdent | Shift+Tab |
| Show Help | Ctrl+/ |

Try editing this content to see all features in action!
`);

const yamlContent = ref({
	name: "Sample Configuration",
	version: "1.0.0",
	settings: {
		enabled: true,
		maxRetries: 3,
		timeout: 30
	},
	features: ["feature-a", "feature-b", "feature-c"]
});

const jsonContent = ref({
	title: "JSON Example",
	description: "This is a sample JSON object for testing",
	metadata: {
		createdAt: "2024-01-15",
		author: "Developer"
	},
	items: [
		{ id: 1, name: "Item One" },
		{ id: 2, name: "Item Two" }
	]
});
</script>
