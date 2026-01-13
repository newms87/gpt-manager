# Task Runners Catalog

## Overview

Task runners are the execution engines that power individual tasks in workflows. Each runner specializes in specific types of processing, from AI-powered analysis to data transformation and system integration.

## AI-Powered Runners

### AgentThreadTaskRunner ("AI Agent")
The primary runner for AI-powered task execution using agents.

**Capabilities:**
- Natural language processing and generation
- JSON schema-based structured output
- Context-aware conversation management
- Multi-modal input processing (text, images, documents)
- MCP (Model Context Protocol) server integration

**Configuration:**
```json
{
  "timeout": 120,                    // Execution timeout in seconds (1-600)
  "include_text_sources": true,      // Append source text to output
  "deduplicate_names": false,        // Remove duplicate names from JSON output
  "mcp_server_id": "server-uuid"     // Optional MCP server integration
}
```

**Use Cases:**
- Content analysis and summarization
- Data extraction from unstructured text
- Creative content generation
- Complex reasoning and decision-making
- Document classification and tagging

**Requirements:**
- Must have agent_id specified
- Supports both text and json_schema response formats
- Can use schema_definition_id for structured output

### ClaudeTaskRunner
Specialized runner for Claude-specific AI processing.

**Capabilities:**
- Optimized for Claude model family
- Advanced reasoning and analysis
- Code generation and review
- Document analysis and synthesis

**Use Cases:**
- Complex analytical tasks
- Code generation and refactoring
- Advanced document processing
- Multi-step reasoning workflows

## Data Input/Output Runners

### WorkflowInputTaskRunner
Entry point runner for workflow data ingestion.

**Capabilities:**
- Captures initial workflow input data
- Supports multiple input formats
- Validates input against expected schemas
- Provides data normalization

**Configuration:**
- No special configuration required
- Automatically handles various input types

**Use Cases:**
- Starting nodes in all workflows
- User input capture
- External data ingestion points
- API endpoint data reception

### WorkflowOutputTaskRunner  
Final stage runner for workflow result formatting.

**Capabilities:**
- Formats final workflow outputs
- Aggregates results from multiple sources
- Applies output templates and formatting
- Handles export format conversion

**Configuration:**
```json
{
  "output_format": "json|text|html",
  "template_id": "optional-template-uuid"
}
```

**Use Cases:**
- Final result presentation
- Report generation
- Data export preparation
- API response formatting

## Database Runners

### LoadFromDatabaseTaskRunner
Retrieves data from database tables.

**Capabilities:**
- Query execution with team scoping
- Relationship loading
- Data filtering and pagination
- Schema-aware data retrieval

**Configuration:**
```json
{
  "model_class": "App\\Models\\YourModel",
  "query_parameters": {},
  "relationships": ["relation1", "relation2"],
  "limit": 1000
}
```

**Use Cases:**
- Loading reference data
- Retrieving related records
- Data synchronization tasks
- Report data gathering

### SaveToDatabaseTaskRunner
Stores processed results to database.

**Capabilities:**
- Model creation and updates
- Bulk data operations
- Relationship management
- Validation and error handling

**Configuration:**
```json
{
  "model_class": "App\\Models\\YourModel",
  "update_mode": "create|update|upsert",
  "validation_rules": {}
}
```

**Use Cases:**
- Persisting analysis results
- Creating structured data records
- Updating existing records
- Data warehouse operations

## File Processing Runners

### LoadCsvTaskRunner
Imports data from CSV files.

**Capabilities:**
- CSV parsing with configurable delimiters
- Header row handling
- Data type conversion
- Error handling for malformed data

**Configuration:**
```json
{
  "delimiter": ",",
  "has_headers": true,
  "encoding": "utf-8",
  "skip_empty_rows": true
}
```

**Use Cases:**
- Data import from external sources
- Batch data processing
- Report data ingestion
- Legacy system integration

### ImageToTextTranscoderTaskRunner
Converts images to text using OCR.

**Capabilities:**
- Optical Character Recognition (OCR)
- Multiple image format support
- Text extraction and cleaning
- Layout preservation options

**Configuration:**
```json
{
  "ocr_engine": "tesseract|google_vision",
  "language": "eng",
  "preserve_layout": false,
  "confidence_threshold": 0.8
}
```

**Use Cases:**
- Document digitization
- Image content analysis
- Form data extraction
- Legacy document processing

## Data Transformation Runners

### FilterArtifactsTaskRunner
Filters and transforms artifact data.

**Capabilities:**
- Rule-based filtering
- Data validation
- Content transformation
- Quality control checks

**Configuration:**
```json
{
  "filter_rules": [],
  "validation_schema": {},
  "transform_operations": [],
  "quality_threshold": 0.9
}
```

**Use Cases:**
- Data quality control
- Content validation
- Selective data passing
- Noise reduction

### SplitArtifactsTaskRunner
Divides artifacts into smaller components.

**Capabilities:**
- Content-based splitting
- Size-based chunking
- Delimiter-based separation
- Overlap control for continuity

**Configuration:**
```json
{
  "split_method": "size|delimiter|content",
  "chunk_size": 1000,
  "delimiter": "\n\n",
  "overlap": 100
}
```

**Use Cases:**
- Large document processing
- Parallel processing preparation
- Content segmentation
- Memory management

### MergeArtifactsTaskRunner
Combines multiple artifacts into single output.

**Capabilities:**
- Content concatenation
- Structured data merging
- Deduplication
- Format normalization

**Configuration:**
```json
{
  "merge_strategy": "concatenate|merge|deduplicate",
  "separator": "\n---\n",
  "preserve_metadata": true,
  "sort_order": "created_at|name|custom"
}
```

**Use Cases:**
- Result aggregation
- Report compilation
- Data consolidation
- Final output preparation

## Classification Runners

### CategorizeArtifactsTaskRunner
Organizes artifacts into categories.

**Capabilities:**
- Rule-based categorization
- Content-based classification
- Hierarchical category support
- Confidence scoring

**Configuration:**
```json
{
  "categories": [],
  "classification_rules": [],
  "confidence_threshold": 0.8,
  "allow_multiple_categories": false
}
```

**Use Cases:**
- Content organization
- Document classification
- Priority assignment
- Workflow routing decisions

### ClassifierTaskRunner
Advanced classification with machine learning.

**Capabilities:**
- AI-powered classification
- Custom model training
- Multi-label classification
- Confidence-based routing

**Configuration:**
```json
{
  "model_type": "naive_bayes|svm|neural_network",
  "training_data": [],
  "features": [],
  "confidence_threshold": 0.85
}
```

**Use Cases:**
- Sophisticated content classification
- Sentiment analysis
- Topic modeling
- Quality scoring

## Specialized Runners

### PageOrganizerTaskRunner
Organizes document pages and structure.

**Capabilities:**
- Page order optimization
- Document structure analysis
- Content flow improvement
- Layout preservation

**Configuration:**
```json
{
  "organization_strategy": "sequential|topical|importance",
  "preserve_original_order": false,
  "page_break_detection": true,
  "merge_related_pages": false
}
```

**Use Cases:**
- Document restructuring
- Report organization
- Content flow optimization
- Multi-page document processing

### SequentialCategoryMatcherTaskRunner
Matches content against sequential category patterns.

**Capabilities:**
- Pattern-based matching
- Sequential rule processing
- Context-aware categorization
- Multi-step classification

**Configuration:**
```json
{
  "patterns": [],
  "matching_strategy": "first_match|best_match|all_matches",
  "context_window": 3,
  "case_sensitive": false
}
```

**Use Cases:**
- Complex pattern matching
- Multi-criteria classification
- Content routing
- Rule-based processing

### ArtifactLevelProjectionTaskRunner
Projects artifacts to different structural levels.

**Capabilities:**
- Data structure transformation
- Level-based filtering
- Hierarchical data manipulation
- Schema projection

**Configuration:**
```json
{
  "source_level": "document|page|paragraph|sentence",
  "target_level": "document|page|paragraph|sentence",
  "aggregation_method": "merge|split|transform",
  "preserve_hierarchy": true
}
```

**Use Cases:**
- Data structure changes
- Hierarchical transformations
- Level-specific processing
- Format conversions

## Workflow Control Runners

### RunWorkflowTaskRunner
Executes nested workflows within tasks.

**Capabilities:**
- Sub-workflow execution
- Parameter passing
- Result integration
- Error propagation

**Configuration:**
```json
{
  "workflow_definition_id": "uuid",
  "parameter_mapping": {},
  "wait_for_completion": true,
  "timeout": 3600
}
```

**Use Cases:**
- Complex nested processing
- Reusable workflow components
- Modular workflow design
- Recursive processing patterns

### TemplateTaskRunner
Renders templates (Google Docs or HTML) with dynamic content.

**Capabilities:**
- Template-based document generation
- Supports Google Docs and HTML template types
- Dynamic variable resolution from artifacts and team objects
- AI-powered variable extraction
- Multiple variable resolution strategies (artifact mapping, team object mapping, AI mapping)

**Configuration:**
```json
{
  "template_stored_file_id": "stored-file-uuid",
  "template_definition_id": "template-uuid"
}
```

**Use Cases:**
- Report generation from templates
- Document automation
- Personalized content creation
- Formatted output production with dynamic data
- HTML document rendering

## Runner Selection Guidelines

### By Task Type

**Analysis Tasks**: AgentThreadTaskRunner, ClassifierTaskRunner
**Data Processing**: LoadFromDatabaseTaskRunner, SaveToDatabaseTaskRunner
**Content Generation**: AgentThreadTaskRunner, TemplateTaskRunner
**Data Transformation**: FilterArtifactsTaskRunner, SplitArtifactsTaskRunner, MergeArtifactsTaskRunner
**File Processing**: LoadCsvTaskRunner, ImageToTextTranscoderTaskRunner
**Classification**: CategorizeArtifactsTaskRunner, ClassifierTaskRunner

### By Performance Requirements

**High-Throughput**: FilterArtifactsTaskRunner, SplitArtifactsTaskRunner
**CPU-Intensive**: AgentThreadTaskRunner, ImageToTextTranscoderTaskRunner
**Memory-Intensive**: MergeArtifactsTaskRunner, LoadFromDatabaseTaskRunner
**I/O-Intensive**: LoadCsvTaskRunner, SaveToDatabaseTaskRunner

### By Data Type

**Text Processing**: AgentThreadTaskRunner, FilterArtifactsTaskRunner
**Structured Data**: LoadFromDatabaseTaskRunner, SaveToDatabaseTaskRunner
**Images**: ImageToTextTranscoderTaskRunner
**Documents**: PageOrganizerTaskRunner, TemplateTaskRunner
**Mixed Content**: MergeArtifactsTaskRunner, CategorizeArtifactsTaskRunner

## Integration Considerations

### Configuration Validation
- Each runner validates its configuration on task creation
- Invalid configurations are rejected with descriptive errors
- Configuration changes trigger validation re-runs

### Performance Characteristics  
- Runners have different resource requirements and performance profiles
- Consider system capacity when selecting runners for high-volume workflows
- Monitor runner performance and adjust configurations as needed

### Error Handling
- All runners implement consistent error handling patterns
- Failures are logged with detailed context information
- Recovery mechanisms vary by runner type and configuration

This catalog provides the foundation for selecting appropriate runners for specific workflow requirements while optimizing for performance, reliability, and maintainability.