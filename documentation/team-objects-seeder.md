# Team Objects Seeder Command

## Overview

The `team-objects:seed` command generates random team objects with hierarchical relationships and rich attributes for testing and demonstration purposes.

## Command Syntax

```bash
php artisan team-objects:seed <team> <schema> [options]
```

## Arguments

- `team` - **Required**: The team ID (number) or team name to seed objects for
- `schema` - **Required**: The schema definition ID (number) or schema name to use for all objects

## Options

- `--count=50` - Number of root objects to create (default: 50)
- `--depth=3` - Maximum nesting depth for relationships (default: 3)

## Examples

### Basic Usage
```bash
# Seed 50 objects for team ID 1 using schema definition ID 5
php artisan team-objects:seed 1 5

# Seed objects for team and schema by name
php artisan team-objects:seed "My Team Name" "Provider Schema"
```

### Custom Configuration
```bash
# Create 100 root objects with deeper nesting
php artisan team-objects:seed 1 5 --count=100 --depth=5

# Minimal seeding for testing
php artisan team-objects:seed 1 "Provider Schema" --count=10 --depth=2
```

## What Gets Created

### Object Types
The seeder creates objects of various types:
- **Demand** - Request, Order, Requisition
- **Provider** - Dr., Nurse, Specialist  
- **Facility** - Medical Center, Hospital, Clinic
- **Diagnosis** - ICD-, DX-, Condition
- **Procedure** - CPT-, PROC-, Surgery
- **Medication** - Med-, RX-, Drug
- **Laboratory** - Lab-, Test-, Panel
- **Radiology** - RAD-, Imaging-, X-Ray
- **Document** - DOC-, File-, Report
- **Address** - Location-, Site-, Address
- **Insurance** - Policy-, Plan-, Coverage
- **Claim** - CLM-, Bill-, Invoice
- **Authorization** - AUTH-, Approval-, PA-
- **Referral** - REF-, Transfer-, Consult
- **Appointment** - APPT-, Visit-, Schedule

### Schema Definitions
- Automatically creates JSON schemas for each object type
- Defines properties for attributes and relationships
- Supports string, number, boolean, and array data types

### Hierarchical Relationships
- **Root Objects**: Top-level objects with no parent
- **Child Objects**: Nested objects up to specified depth
- **Cross-References**: Additional relationships between unrelated objects
- **Relationship Names**: providers, facilities, diagnoses, procedures, etc.

### Rich Attributes
Each object gets 3-10 random attributes with:
- **Values**: Contextual data based on attribute name and type
- **Confidence Levels**: High, Medium, Low, or null
- **Explanations**: Reasoning for attribute derivation
- **Data Types**: Text, JSON arrays/objects, numbers, booleans, dates

### Example Attribute Types
- `status` - active, pending, completed, cancelled, on-hold
- `priority` - 1-10 numeric priority
- `amount` - Currency values for financial attributes
- `code` - Formatted codes like "ABC-1234-XY"
- `date` - ISO date strings for temporal attributes
- `notes` - Paragraph descriptions
- `is_active` - Boolean flags

## Output

The command provides detailed feedback:

```
Seeding team objects for team: My Team Name
Creating 50 root objects with max depth 3...
Progress bar: 50/50 [============================] 100%

Creating cross-references between objects...

Seeding complete!
+---------------------------+-------+
| Metric                    | Count |
+---------------------------+-------+
| Total Objects            | 127   |
| Total Attributes         | 891   |
| Total Relationships      | 89    |
| Average Attributes/Object | 7.02  |
| Average Relationships/Obj | 0.70  |
+---------------------------+-------+
```

## Data Structure

### Object Hierarchy Example
```
Demand "Request Johnson 12AB34"
├── Provider "Dr. Smith 56CD78" 
│   ├── Facility "Medical Center Wilson 90EF12"
│   │   └── Address "Location Brown 34GH56"
│   └── Diagnosis "ICD- Taylor 78IJ90"
└── Authorization "AUTH- Davis 12KL34"
```

### Sample Attributes
```php
// For a Provider object
[
    'specialty' => 'Cardiology',
    'license_number' => 'LIC-12345-AB',
    'years_experience' => '15',
    'is_primary' => 'true',
    'status' => 'active',
    'quality_rating' => '87'
]
```

## Use Cases

### Development Testing
- Populate development databases with realistic data
- Test UI components with varied data structures
- Performance testing with large datasets

### Demonstration
- Showcase hierarchical data visualization
- Demo filtering and search capabilities  
- Present relationship navigation features

### QA Testing
- Generate consistent test datasets
- Test edge cases with deep nesting
- Validate attribute handling across types

## Performance Notes

- Large datasets (--count > 100) may take several minutes
- Deep nesting (--depth > 4) significantly increases object count
- High attribute counts (--attributes > 15) increase database size
- Consider database constraints when generating large datasets

## Cleanup

To remove seeded data:

```bash
# Delete all objects for a team (replace 1 with team ID)
php artisan tinker
>>> App\Models\TeamObject\TeamObject::where('team_id', 1)->delete();
>>> App\Models\Schema\SchemaDefinition::where('team_id', 1)->where('type', 'TeamObject')->delete();
```

## Integration with UI

The seeded data works perfectly with the new TeamObject UI:
- Navigate to `/team-objects-demo` to explore the generated data
- Use filters to focus on specific object types or confidence levels
- Test hierarchical navigation with the nested relationships
- Examine attribute details and confidence indicators