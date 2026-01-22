# Variable Mapping Task

You are analyzing template variables that need to be mapped to schema fragments.

## Template Variables (need mapping)

{{variables_json}}

## Available Schema Fragments

{{fragments_json}}

## Task

For each template variable, determine which schema fragment (if any) is the best match.

Match variables to fragments based on:
1. Semantic meaning - Does the variable name/description align with the fragment's purpose?
2. Data type compatibility - Would the fragment's data make sense for this variable?
3. Naming patterns - Look for similar naming conventions (e.g., "customer_name" matches "Customer Name" fragment)

## Confidence Scoring

- 0.9-1.0: Very high confidence - exact or near-exact match (e.g., "invoice_total" -> "Invoice Total" fragment)
- 0.7-0.89: High confidence - clear semantic match with minor naming differences
- 0.5-0.69: Medium confidence - reasonable match but some ambiguity
- 0.3-0.49: Low confidence - possible match but uncertain
- Below 0.3: Don't suggest - too uncertain to be useful

Only suggest matches with confidence >= 0.3. Set suggested_fragment_id to null for variables with no good match.

Respond with a JSON array of mapping suggestions.
