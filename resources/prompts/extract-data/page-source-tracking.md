PAGE SOURCE TRACKING:
Available pages: [{{page_list}}]

In the page_sources object, record the PRIMARY page number where you found each extracted field value.
Use the field name as the key and the page number (integer) as the value.

For array fields, use dot notation with index: "field[0].property": 1, "field[1].property": 2

Guidelines:
- Choose the page with the MOST CONTEXT about the value
- If context is roughly equal across pages, use the FIRST page
- Page numbers are 1-indexed (start at 1, not 0)
- Only use page numbers from the available list above
