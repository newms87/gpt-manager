# WorkflowDefinition Structure Guide

## Overview

A WorkflowDefinition is the foundational model that defines the complete structure and behavior of a workflow in the GPT Manager system. It serves as the blueprint that orchestrates how tasks are connected and executed.

## Core Properties

### Basic Information
- **name**: Unique identifier and display name for the workflow (max 80 chars)
- **description**: Detailed explanation of the workflow's purpose and functionality
- **team_id**: Automatically scoped to the current team for access control

### Execution Control
- **max_workers**: Maximum number of parallel task processes that can run simultaneously
  - Default: 20
  - Controls resource utilization and prevents system overload
  - Consider system capacity when setting this value

## Relationships and Structure

### WorkflowNodes
- A workflow consists of multiple WorkflowNodes that reference TaskDefinitions
- Each node represents a single step or task in the workflow
- Nodes are connected via WorkflowConnections to define execution flow

### WorkflowConnections
- Define how data flows between nodes
- Specify source and target relationships
- Control artifact passing between tasks

### WorkflowRuns
- Instances of workflow execution
- Track progress, status, and results of workflow runs
- Maintain history of all executions

## Key Design Patterns

### Starting Nodes
Starting nodes are automatically identified as nodes that:
- Have no incoming connections (no connectionsAsTarget)
- Use WorkflowInputTaskRunner as their task runner
- Serve as entry points for workflow execution

### Node Organization
- Workflows follow a directed acyclic graph (DAG) structure
- No circular dependencies allowed
- Clear input → processing → output flow

### Parallel Execution
- The max_workers setting enables parallel task processing
- Multiple nodes can execute simultaneously if not dependent on each other
- System automatically manages task queuing and resource allocation

## Workflow Building Best Practices

### Planning Phase
When designing a workflow:
1. Identify clear input requirements
2. Define expected output format
3. Break down processing into logical steps
4. Consider parallel execution opportunities
5. Plan error handling and recovery

### Node Naming
- Use descriptive names that clearly indicate the node's purpose
- Follow consistent naming conventions within the workflow
- Consider the workflow as a readable process diagram

### Connection Strategy
- Minimize unnecessary connections to reduce complexity
- Group related processing steps when possible
- Consider data transformation needs between nodes

## System Integration

### Team-Based Access Control
- All workflows are automatically scoped to teams
- Users can only access workflows within their team
- Team isolation is enforced at the database level

### Resource Management
- max_workers setting directly impacts system performance
- Higher values enable more parallelism but consume more resources
- Monitor system capacity when designing high-throughput workflows

### Audit Trail
- All workflow modifications are tracked via AuditableTrait
- Changes are logged for compliance and debugging
- Version history enables rollback capabilities

## Common Workflow Patterns

### Linear Processing
```
Input → Process → Transform → Output
```
Simple sequential processing with single-threaded execution.

### Parallel Processing
```
Input → Split → [Process A, Process B, Process C] → Merge → Output
```
Data is split and processed in parallel, then merged back together.

### Conditional Branching
```
Input → Classify → [Branch A, Branch B] → Conditional Merge → Output
```
Different processing paths based on data classification.

### Iterative Refinement
```
Input → Process → Validate → [Refine Loop] → Final Output
```
Multiple passes through processing with validation and refinement.

## Error Handling Considerations

### Corruption Detection
The system includes automatic detection and cleanup of:
- Orphaned connections referencing deleted nodes
- Invalid node references
- Circular dependency detection

### Recovery Mechanisms
- Failed workflow runs maintain state for debugging
- Individual task failures don't necessarily fail entire workflow
- Retry mechanisms available for transient failures

## Performance Optimization

### Resource Allocation
- Set max_workers based on available system resources
- Consider memory requirements of individual tasks
- Monitor CPU utilization during peak usage

### Connection Efficiency
- Minimize artifact size in connections where possible
- Use appropriate artifact modes for data flow
- Consider caching for frequently accessed data

This structure provides the foundation for building robust, scalable workflows that can handle complex business processes while maintaining system reliability and performance.