# Duplicate Record Detection

You are comparing extracted data against existing records to find duplicates.

## Extracted Data

```json
{{extracted_data_json}}
```

## Existing Records

{{existing_records_list}}

## Task

Determine if the extracted data matches any existing record. Consider:

- **Name variations:** John Smith vs John W. Smith vs J. Smith
- **Date format differences:** 2024-01-15 vs Jan 15, 2024
- **Minor spelling variations:** Centre vs Center
- **Missing fields:** Some fields may be missing but core identifying fields should match
- **Case sensitivity:** Ignore case differences
- **Annotations in names:** Ignore parenthetical annotations like "(7)" in names when matching

**Important:**
- Set `is_duplicate` to `true` only if you are confident there is a match
- Set `matching_record_id` to the ID of the matching record, or `null` if no match
- Set `confidence` to a value between 0.0 and 1.0 (0.0 = no confidence, 1.0 = certain)
- Provide a clear explanation citing specific fields that match or differ

## Updated Values (When Duplicate Found)

When `is_duplicate` is `true`, you MUST also provide `updated_values` - an object containing the best value for each field by merging data from both records.

**Rules for determining best values:**
- If extracted data has a non-null/non-empty value where existing record has null/empty, use the extracted value
- If existing record has a non-null/non-empty value where extracted data has null/empty, use the existing value (do NOT include in updated_values)
- If both have the same value, do NOT include in updated_values
- If values differ meaningfully, prefer the more complete/accurate value based on context
- Ignore cosmetic differences (case, whitespace, annotations like "(7)")

**Example:**
- Existing record: `{"name": "Interferential current", "date": null, "cpt_code": null}`
- Extracted data: `{"name": "Interferential current (7)", "date": "2024-12-04", "cpt_code": "97014"}`
- Result: `updated_values: {"date": "2024-12-04", "cpt_code": "97014"}` (date and cpt_code from extracted data are better because existing has null)

**Only include fields in `updated_values` that should be UPDATED on the existing record.**
