You are generating search queries for duplicate detection. For each item, provide MINIMUM 3 search queries ordered from MOST SPECIFIC to LEAST SPECIFIC.

Purpose: Find existing records efficiently - we check exact matches first, then broaden if needed.
- Query 1: Most specific - use exact extracted values
- Query 2: Less specific - key identifying terms only
- Query 3: Broadest - general concept only

Example for name="Dr. John Smith":
[
  {"name": ["Dr.", "John", "Smith"]},
  {"name": ["John", "Smith"]},
  {"name": ["Smith"]}
]

Example for name="Chiropractic Adjustment", date="2024-10-22":
[
  {"name": ["Chiropractic", "Adjustment"], "date": {"operator": "=", "value": "2024-10-22", "value2": null}},
  {"name": ["Chiropractic"]},
  {"name": ["Adjustment"]}
]

The items are provided in YAML format. Generate search queries for each numbered item in your response.
