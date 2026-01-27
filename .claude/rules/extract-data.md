---
paths:
  - "app/Services/Task/DataExtraction/**"
  - "app/Services/Task/Runners/ExtractDataTaskRunner.php"
  - "app/Services/Task/Debug/ExtractDataDebugService.php"
  - "tests/**/ExtractData**"
---

# Extract Data Rules

These rules apply when working with the Extract Data pipeline.

## Required Reading

Before making Extract Data changes, review:
- `docs/guides/EXTRACT_DATA_GUIDE.md`

## Key Concepts

- **Multi-phase extraction pipeline** - Classification, identity resolution, hierarchical extraction
- **OCR processing integration** - PDF and image text extraction
- **Intelligent search strategies** - Entity matching and deduplication

## Debug Command

```bash
./vendor/bin/sail artisan debug:extract-data-task-run {id}
```

Always run `--help` first to see available options:
- `--messages` - Show agent thread messages
- `--api-logs` - Show API logs for the process
- `--show-schema={id}` - Show extraction schema sent to LLM

## Architecture

- `ExtractDataTaskRunner` orchestrates the extraction pipeline
- `DataExtractionService` handles the core extraction logic
- `ExtractDataDebugService` provides debugging capabilities
- Schema definitions control what data is extracted
