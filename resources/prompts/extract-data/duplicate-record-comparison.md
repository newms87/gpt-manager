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

**Important:**
- Set `is_duplicate` to `true` only if you are confident there is a match
- Set `matching_record_id` to the ID of the matching record, or `null` if no match
- Set `confidence` to a value between 0.0 and 1.0 (0.0 = no confidence, 1.0 = certain)
- Provide a clear explanation citing specific fields that match or differ
