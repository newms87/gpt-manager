# Remaining Fields Grouping Task

You are tasked with grouping remaining fields for data extraction.

## Object Type Information

**Name:** {{name}}
**Path:** {{path}}
**Level:** {{level}}

## Remaining Fields to Group

These fields were NOT included in the identity skim group and need to be organized:

{{fields_yaml}}

## Configuration

- **Group Max Points:** {{group_max_points}} (maximum fields per group)

## Your Task

Create logical extraction groups for these remaining fields:

1. **Group Related Fields:** Organize fields into logical groups based on:
   - Semantic similarity (e.g., address fields together)
   - Document structure (e.g., fields likely found in same section)
   - Data type or purpose

2. **Assign Search Modes:** For each group, choose an appropriate search_mode:
   - **skim:** For singular values that can be resolved the first time they're encountered.
     Examples: names, dates, locations, IDs - things with ONE particular value that just needs to be found.
     Use when the field's value won't change or accumulate as you read more pages.
   - **exhaustive:** For values that could be added to by information across multiple pages.
     Examples: assessments of a patient, all findings in a report, items in a list.
     Use when the field's value might be augmented by additional information on different pages.
   - **Key distinction:** If it's a singular value resolved once, use skim. If it accumulates over multiple pages, use exhaustive.

3. **Respect Size Limits:** Each group should not exceed {{group_max_points}} fields.

4. **Name Groups Descriptively:** Use clear, meaningful names for each group.

5. **Describe Each Group:** Provide a clear description for each group that explains:
   - What type of document content or sections would contain these fields
   - What visual or textual cues to look for on a page
   - This description helps classify pages by relevance to the group

Generate your extraction groups now.
