# Workflow Connections Guide

## Overview

WorkflowConnections define the data flow between nodes in a workflow, establishing the execution sequence and artifact passing relationships. They create the directed acyclic graph (DAG) that determines how tasks are executed and how data moves through the workflow.

## Connection Structure

### Core Properties
- **workflow_definition_id**: Links connection to its parent workflow
- **source_node_id**: The node that produces output data
- **target_node_id**: The node that receives input data
- **name**: Optional descriptive name for the connection
- **source_output_port**: Specific output port from source node (optional)
- **target_input_port**: Specific input port on target node (optional)

### Relationship Management
- Connections create parent-child relationships between artifacts
- Source node artifacts become parents of target node artifacts
- This hierarchy enables traceability throughout the workflow

## Connection Types

### Sequential Connections
Direct node-to-node connections for linear processing:
```
Node A → Node B → Node C
```
Each node waits for the previous node to complete before starting.

### Parallel Branching
Single source feeding multiple targets:
```
Node A → [Node B, Node C, Node D]
```
Multiple nodes can process the same input data simultaneously.

### Convergent Processing
Multiple sources feeding single target:
```
[Node A, Node B, Node C] → Node D
```
Target node waits for all source nodes to complete before starting.

### Complex Topologies
Combination of branching and convergence:
```
Input → Split → [Process A, Process B] → Merge → Output
      ↓
    Archive
```

## Port System

### Output Ports
Source nodes can specify multiple output ports:
- **Default port**: Empty string or null (most common)
- **Named ports**: Specific output categories or types
- Used when tasks generate multiple types of output

### Input Ports  
Target nodes can specify which input port receives data:
- **Default port**: Accepts all incoming data
- **Named ports**: Specific input channels for different data types
- Enables selective data routing

### Port Matching
- Connections link specific source output ports to target input ports
- Unspecified ports default to standard input/output channels
- Port names must match between source and target for proper routing

## Artifact Flow Patterns

### Standard Flow
```
Source: One artifact → Target: Processes one artifact
```
Simple 1:1 artifact relationship.

### Split Flow
```
Source: One artifact → Target: Creates multiple processes
```
Used when target has `input_artifact_mode: "split"`.

### Merge Flow
```
Source: Multiple artifacts → Target: Single processing unit
```
Target processes all source artifacts together.

### Hierarchical Flow
```
Parent artifacts maintain relationships with child artifacts
Created artifacts reference their source artifacts as parents
```

## Connection Validation

### Structural Validation
- No circular dependencies (DAG requirement)
- All referenced nodes must exist in the same workflow
- Source and target nodes must be different

### Logical Validation
- Compatible artifact modes between connected nodes
- Matching input/output port specifications
- Appropriate data type compatibility

### System Validation
- Automatic cleanup of corrupted connections
- Orphaned connection detection and removal
- Node deletion cascades to remove associated connections

## Data Compatibility

### Artifact Mode Compatibility

**Source Standard → Target Standard:**
- Direct 1:1 artifact passing
- Most common connection type

**Source Standard → Target Split:**
- Single artifact becomes input for parallel processing
- Target creates multiple processes from one input

**Source Split → Target Standard:**
- Multiple source artifacts processed together by target
- Target receives all artifacts as input set

**Source Split → Target Split:**
- Each source artifact generates separate target processing
- Maintains 1:1 relationship through split processing

### Schema Compatibility
- JSON schema outputs must be compatible with target expectations
- Text outputs can generally connect to any text-accepting input
- Mixed format connections require careful consideration

## Performance Implications

### Parallel Execution
- Connections determine maximum parallelism potential
- Independent branches can execute simultaneously
- Convergent nodes create synchronization points

### Resource Management
- Multiple incoming connections can create resource bottlenecks
- Consider system capacity when designing complex topologies
- Monitor memory usage for large artifact transfers

### Execution Efficiency
- Minimize unnecessary data copying between nodes
- Use appropriate artifact levels to filter data
- Consider caching for frequently accessed connections

## Common Connection Patterns

### Linear Pipeline
```
Input → Validate → Process → Transform → Output
```
Sequential processing with clear data transformation steps.

### Fan-Out Processing
```
Input → Distribute → [Analyze A, Analyze B, Analyze C] → Collect → Output
```
Parallel analysis of the same data with result aggregation.

### Conditional Processing
```
Input → Classify → [Path A, Path B] → Merge → Output
```
Different processing paths based on data classification.

### Multi-Stage Processing
```
Input → Stage1 → [Branch A, Branch B] → Stage2 → Output
              ↓
           Validation
```
Complex workflows with validation and branching.

## Best Practices

### Connection Design
1. **Minimize complexity**: Prefer simple, clear connection patterns
2. **Plan for scalability**: Consider how connections will perform under load
3. **Maintain traceability**: Ensure artifact relationships are clear
4. **Document intent**: Use descriptive connection names when needed

### Error Handling
1. **Plan for failures**: Design connections that can handle node failures gracefully
2. **Avoid bottlenecks**: Don't create single points of failure in connection topology
3. **Enable recovery**: Design connections that support retry mechanisms

### Performance Optimization
1. **Reduce data movement**: Minimize unnecessary artifact copying
2. **Balance parallelism**: Don't over-parallelize without considering resources
3. **Monitor bottlenecks**: Identify and address connection performance issues
4. **Use appropriate modes**: Match artifact modes to actual data flow needs

### Debugging Support
1. **Clear naming**: Use descriptive names for complex connections
2. **Logical grouping**: Organize connections in understandable patterns
3. **Documentation**: Document complex connection logic for maintainability

## Connection Lifecycle

### Creation
- Connections are created when workflow structure is defined
- Validation occurs immediately upon creation
- Invalid connections are rejected before saving

### Execution
- Connections activate when source nodes complete
- Artifact relationships are established dynamically
- Target nodes receive notifications when input is available

### Maintenance
- System automatically cleans corrupted connections
- Connection validation runs during workflow execution
- Failed connections are logged for debugging

### Deletion
- Connections are automatically removed when nodes are deleted
- Cascading deletion maintains referential integrity
- Orphaned connections are detected and cleaned up

This connection system provides the flexibility to create complex, efficient workflows while maintaining data integrity and system performance.