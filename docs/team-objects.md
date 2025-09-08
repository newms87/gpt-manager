# Team Objects System Documentation

## Overview

The Team Objects system is a flexible, hierarchical data structure management system that allows for the creation, organization, and manipulation of complex nested object relationships with rich metadata and AI-generated attribute tracking.

## Core Architecture

### Database Schema

#### `team_objects` Table
- **Primary Fields**: `id`, `team_id`, `type`, `name`, `description`, `date`, `url`, `meta`
- **Schema Integration**: `schema_definition_id` → Links to JSON schema defining object structure
- **Hierarchical Structure**: `root_object_id` → Self-referencing for parent-child relationships
- **Soft Deletes**: `deleted_at` for data preservation
- **Auditing**: Created/updated timestamps with team-based access control

#### `team_object_relationships` Table
- **Flexible Relationships**: Many-to-many relationships between any two objects
- **Named Relations**: `relationship_name` field allows semantic relationship types
- **Examples**: "providers", "facilities", "addresses", "diagnoses", etc.
- **Unique Constraints**: Prevents duplicate relationships between same objects

#### `team_object_attributes` Table
- **Rich Metadata Storage**: Beyond simple key-value pairs
- **Value Types**: Both `text_value` and `json_value` for different data types
- **AI Integration**: `agent_thread_run_id` links to AI generation context
- **Quality Metrics**: `confidence` level and `reason` explanation
- **Unique Names**: One attribute per name per object

#### `team_object_attribute_sources` Table
- **Source Tracking**: Each attribute can reference multiple sources
- **File Sources**: Links to `stored_files` for document-based attributes
- **Message Sources**: Links to `agent_thread_messages` for AI-generated content
- **Explanation**: Contextual information about how the attribute was derived

### Model Relationships

```php
TeamObject:
├── belongsTo: SchemaDefinition (schema_definition_id)
├── belongsTo: TeamObject (root_object_id) // Parent object
├── hasMany: TeamObjectRelationship (team_object_id)
├── hasMany: TeamObjectRelationship (related_team_object_id) // Reverse relations
├── hasMany: TeamObjectAttribute (team_object_id)
└── hasManyThrough: TeamObject via TeamObjectRelationship // Related objects

TeamObjectAttribute:
├── belongsTo: TeamObject (team_object_id)
├── belongsTo: AgentThreadRun (agent_thread_run_id)
└── hasMany: TeamObjectAttributeSource (team_object_attribute_id)

TeamObjectAttributeSource:
├── belongsTo: TeamObjectAttribute (team_object_attribute_id)
├── belongsTo: StoredFile (stored_file_id)
└── belongsTo: AgentThreadMessage (agent_thread_message_id)
```

## Schema Definition Integration

### JSON Schema Structure
Team Objects use JSON schemas to define:
- **Object Properties**: What attributes an object type should have
- **Data Types**: String, number, boolean, array, object validation
- **Relationships**: How objects relate to other objects
- **UI Presentation**: Titles, descriptions, formatting hints

### Schema Formats Supported
- **JSON**: Standard JSON Schema format
- **YAML**: Human-readable schema definitions  
- **TypeScript**: Type-safe schema definitions

### Schema Properties Interpretation
```typescript
{
  "type": "object",
  "properties": {
    // Scalar properties become attributes
    "diagnosis_code": { "type": "string", "title": "Diagnosis Code" },
    "amount": { "type": "number", "format": "currency" },
    "is_active": { "type": "boolean" },
    
    // Array properties become many-to-many relationships
    "providers": { 
      "type": "array", 
      "items": { "$ref": "#/definitions/Provider" }
    },
    
    // Object properties become one-to-one relationships
    "primary_facility": { "$ref": "#/definitions/Facility" }
  }
}
```

## Data Flow & API Structure

### TeamObjectResource Output
```typescript
{
  // Core object data
  id: number,
  type: string,
  name: string, 
  description: string | null,
  date: string | null,
  url: string | null,
  meta: object | null,
  created_at: string,
  updated_at: string,
  schema_definition_id: number,
  
  // Processed attributes with metadata
  attributes: {
    [attributeName]: {
      id: number,
      name: string,
      value: any, // text_value || json_value
      confidence: string, // "High" | "Medium" | "Low" | null
      reason: string, // Explanation of attribute derivation
      sources: TeamObjectAttributeSource[], // File/message sources
      thread_url: string | null, // Link to AI generation thread
      created_at: string,
      updated_at: string
    }
  },
  
  // Related objects by relationship name
  relations: {
    [relationshipName]: TeamObject[] // Nested objects with same structure
  }
}
```

### Attribute Processing Logic
- **Primary Attributes**: `name`, `description`, `date`, `url` stored directly on object
- **Meta Attributes**: All other schema-defined properties stored as separate records
- **Conflict Resolution**: Most recent attributes take precedence by date
- **Source Aggregation**: Multiple sources per attribute with explanations

## Hierarchical Structure Examples

### Healthcare Use Case
```
Demand (Root)
├── providers[] (Relationship)
│   ├── Provider A
│   │   ├── diagnosis_codes[] (Relationship)
│   │   │   └── Diagnosis Code XYZ
│   │   └── radiology[] (Relationship)
│   │       └── Radiology Service
│   │           └── facility (Relationship)
│   │               └── Medical Facility
│   │                   └── addresses[] (Relationship)
│   │                       └── Facility Address
│   └── Provider B
│       └── ... (similar nested structure)
```

### Nesting Depth
- **Maximum Depth**: 5+ levels supported
- **Performance**: All data loaded with relationships (no N+1 queries)
- **Navigation**: Each level can expand/collapse independently

## AI Integration

### Attribute Generation
- **Source Tracking**: Each AI-generated attribute links to the agent thread run
- **Confidence Scoring**: AI provides confidence levels for generated content
- **Reasoning**: Explanation of how the attribute value was determined
- **Multiple Sources**: Single attribute can combine data from multiple sources

### Quality Metadata
- **Confidence Levels**:
  - `High`: AI is confident in the accuracy
  - `Medium`: Some uncertainty or inferential reasoning
  - `Low`: Low confidence or incomplete data
  - `null`: No confidence assessment available

### Source Attribution
- **File Sources**: Attributes derived from uploaded documents
- **Message Sources**: Attributes from AI conversation context
- **Explanations**: Human-readable explanation of derivation process

## Usage Patterns

### Object Creation
```php
$teamObject = TeamObject::create([
    'team_id' => $team->id,
    'type' => 'Provider',
    'name' => 'Medical Center ABC',
    'schema_definition_id' => $providerSchema->id
]);

// Add attributes
$teamObject->attributes()->create([
    'name' => 'specialty',
    'text_value' => 'Cardiology',
    'confidence' => 'High',
    'reason' => 'Explicitly stated in provider directory'
]);
```

### Relationship Creation
```php
// Create relationship between objects
TeamObjectRelationship::create([
    'team_object_id' => $demand->id,
    'related_team_object_id' => $provider->id,
    'relationship_name' => 'providers'
]);
```

### Complex Queries
```php
// Load object with all relationships and attributes
$teamObject = TeamObject::with([
    'attributes.sources.sourceFile',
    'attributes.sources.sourceMessage',
    'relationships.related'
])->find($id);
```

## Validation & Business Rules

### Object Validation
- **Uniqueness**: Objects with same type + name + schema must be unique within team
- **Schema Compliance**: Attributes must match schema definition requirements
- **Relationship Integrity**: Related objects must exist and be accessible to team

### Attribute Validation
- **Name Uniqueness**: One attribute per name per object
- **Value Types**: Must match schema-defined types
- **Source Integrity**: Sources must be accessible to the team

### Cascade Operations
- **Object Deletion**: Soft deletes with cascade to attributes and relationships
- **Relationship Cleanup**: Removing relationships cleans up both directions
- **Schema Changes**: Schema updates don't break existing objects

## Performance Considerations

### Data Loading
- **Eager Loading**: All relationships loaded to avoid N+1 queries
- **Frontend Caching**: Full object trees cached client-side
- **Pagination**: Applied client-side for responsive filtering

### Optimization Strategies
- **Selective Loading**: Load only required relationship depths
- **Index Usage**: Optimized indexes on team_id, type, name combinations
- **Soft Delete Performance**: Indexes account for deleted_at filtering

## Security & Access Control

### Team-Based Isolation
- **Data Scoping**: All queries automatically scoped to user's team
- **Cross-Team Prevention**: No access to other teams' objects
- **Audit Trails**: All changes tracked with user attribution

### API Security
- **Authentication**: Bearer token authentication required
- **Authorization**: Team membership validation on all operations
- **Input Validation**: Schema-based validation prevents malformed data

## Future Extensibility

### Schema Evolution
- **Versioning**: Schema definitions support version tracking
- **Migration Support**: Automated migration tools for schema changes
- **Backward Compatibility**: Existing objects continue working with schema updates

### Integration Points
- **External APIs**: Objects can reference external system IDs
- **File Attachments**: Rich file source integration
- **AI Enhancement**: Expandable AI integration for automated attribute generation

## Best Practices

### Schema Design
- Keep schemas focused and cohesive
- Use clear, descriptive property names
- Define appropriate data types and formats
- Plan relationship hierarchies carefully

### Object Management
- Use meaningful object names and descriptions
- Maintain consistent typing conventions
- Document complex attribute derivations
- Regular cleanup of orphaned relationships

### Performance Optimization
- Load complete object trees when displaying
- Use client-side filtering for responsive UX
- Cache frequently accessed schema definitions
- Monitor query performance with relationship depths