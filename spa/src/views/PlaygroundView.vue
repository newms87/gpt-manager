<template>
  <div class="p-6 h-full overflow-auto">
    <h1 class="text-2xl font-bold mb-6 text-slate-200">Component Playground</h1>

    <div class="grid grid-cols-2 gap-6">
      <!-- Markdown Input Editor -->
      <div class="flex flex-col">
        <h2 class="text-lg font-semibold mb-3 text-slate-300">Markdown Input</h2>
        <textarea
          v-model="markdownContent"
          class="flex-1 min-h-[600px] p-4 bg-slate-800 text-slate-200 rounded-lg border border-slate-600 font-mono text-sm resize-none"
          placeholder="Enter markdown content here..."
        />
      </div>

      <!-- CodeViewer Output -->
      <div class="flex flex-col">
        <h2 class="text-lg font-semibold mb-3 text-slate-300">CodeViewer Output (format="markdown")</h2>
        <div class="flex-1 min-h-[600px] bg-slate-800 rounded-lg border border-slate-600 overflow-auto">
          <CodeViewer
            :model-value="markdownContent"
            format="markdown"
            editor-class="p-4"
          />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from "vue";
import { CodeViewer } from "quasar-ui-danx";

const markdownContent = ref(`# Markdown Rendering Test

This playground tests the CodeViewer component's markdown rendering capabilities.

## Headings

# Heading 1
## Heading 2
### Heading 3
#### Heading 4
##### Heading 5
###### Heading 6

## Paragraphs and Line Breaks

This is a paragraph with some text.
This line should have a line break before it.

This is a new paragraph separated by a blank line.

## Code Blocks

### JSON Code Block

\`\`\`json
{
  "name": "example",
  "version": "1.0.0",
  "nested": {
    "key": "value",
    "array": [1, 2, 3]
  }
}
\`\`\`

### YAML Code Block

\`\`\`yaml
name: example
version: 1.0.0
nested:
  key: value
  array:
    - 1
    - 2
    - 3
\`\`\`

### TypeScript Code Block

\`\`\`typescript
interface User {
  id: number;
  name: string;
  email: string;
}

function greet(user: User): string {
  return \`Hello, \${user.name}!\`;
}

const user: User = { id: 1, name: "Alice", email: "alice@example.com" };
console.log(greet(user));
\`\`\`

## Blockquotes

> This is a simple blockquote.

> This is a blockquote
> that spans multiple lines
> with **bold** and *italic* text inside.

> Nested content in blockquotes:
>
> - List item 1
> - List item 2
>
> And some \`inline code\` too.

## Unordered Lists

Using dashes:
- Item one
- Item two
- Item three

Using asterisks:
* First item
* Second item
* Third item

Using plus signs:
+ Alpha
+ Beta
+ Gamma

## Ordered Lists

1. First step
2. Second step
3. Third step

Starting from a different number:
5. Fifth item
6. Sixth item
7. Seventh item

## Horizontal Rules

---

***

___

## Inline Formatting

**Bold text** using double asterisks.

*Italic text* using single asterisks.

***Bold and italic*** using triple asterisks.

\`Inline code\` using backticks.

## Links and Images

[Link to Google](https://www.google.com)

[Link with title](https://www.example.com "Example Website")

![Alt text for image](https://via.placeholder.com/150 "Placeholder image")

## Mixed Content

Here's a paragraph with **bold**, *italic*, and \`code\` inline elements, followed by a list:

1. First item with **bold**
2. Second item with *italics*
3. Third item with \`code\`

> A blockquote containing a [link](https://example.com) and some **emphasis**.

## Edge Cases

Empty code block:
\`\`\`
\`\`\`

Single line code block:
\`\`\`bash
echo "Hello World"
\`\`\`

Code block without language:
\`\`\`
Plain text in a code block
with multiple lines
\`\`\`

Consecutive blockquotes:
> First quote

> Second quote

Inline code with special characters: \`<div class="example">\`
`);
</script>
