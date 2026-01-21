# Identity Field Selection Task

You are tasked with selecting identity fields for a specific object type in a data extraction schema.

## Object Type Information

**Name:** {{name}}
**Path:** {{path}}
**Level:** {{level}}
{{parent_type_line}}**Is Array:** {{is_array}}

## Available Simple Fields

These are the simple (non-nested) fields available for this object type:

{{fields_yaml}}

## Configuration

- **Group Max Points:** {{group_max_points}} (maximum fields per group)

## Your Task

1. **Select Identity Fields:** Choose fields that uniquely identify this object type.
   - Prefer fields like name, date, ID, or unique identifiers
   - These fields should help distinguish one instance from another
   - For array types, identity is especially important
   - Use your judgment to determine how many identity fields are needed based on the schema

2. **Select Skim Fields:** Choose additional simple fields to extract together with identity fields in "skim" mode.
   - Include ALL identity fields in skim_fields
   - Add other simple fields that are quick to extract
   - Total skim fields should not exceed group_max_points ({{group_max_points}})
   - These will be extracted in a single pass with the identity fields

3. **Select Search Mode:** Choose the appropriate search_mode for this identity group:
   - **skim:** For singular values that can be resolved the first time they're encountered.
     Examples: names, dates, locations, IDs - things with ONE particular value that just needs to be found.
     Use when the field's value won't change or accumulate as you read more pages.
   - **exhaustive:** For values that could be added to by information across multiple pages.
     Examples: assessments of a patient, all findings in a report, items in a list.
     Use when the field's value might be augmented by additional information on different pages.
   - **Key distinction:** If it's a singular value resolved once, use skim. If it accumulates over multiple pages, use exhaustive.

4. **Provide Reasoning:** Briefly explain why you chose these identity fields.

5. **Describe Identification Content:** Provide a clear description that explains:
   - What type of document content or sections would contain the identity fields
   - What visual or textual cues to look for on a page
   - This description helps classify pages by relevance for identifying this object type

Generate your response now.
