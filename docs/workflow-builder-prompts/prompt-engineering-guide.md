# Prompt Engineering Guide for Workflow Tasks

## Overview

Effective prompt engineering is critical for successful workflow task execution. Well-crafted prompts ensure consistent, high-quality outputs while minimizing errors and maximizing AI agent capabilities.

## Prompt Structure Framework

### Essential Components

**1. Objective Statement**
- Clear, specific goal definition
- Measurable success criteria  
- Explicit scope boundaries
- Action-oriented language

**2. Context Information**
- Background information relevant to the task
- Domain-specific knowledge required
- Constraints and limitations
- Relationship to other workflow steps

**3. Input Description**
- Format and structure of input data
- Expected input variations
- Data quality expectations
- Handling instructions for edge cases

**4. Output Requirements**
- Exact format specification
- Required vs. optional elements
- Quality standards and criteria
- Schema compliance (for structured outputs)

**5. Examples and Patterns**
- Sample inputs with expected outputs
- Common patterns to recognize
- Edge case handling examples
- Quality benchmarks

### Template Structure
```
## Objective
[Clear statement of what needs to be accomplished]

## Context
[Relevant background information and constraints]

## Input
[Description of input format and expectations]

## Processing Instructions
[Step-by-step guidance for the task]

## Output Requirements
[Exact specification of expected output]

## Examples
[Sample inputs and outputs]

## Quality Criteria
[Standards for acceptable output]
```

## Task-Specific Prompt Patterns

### Analysis Tasks

**Pattern**: Analyze → Extract → Structure → Validate

```
Analyze the provided document and extract key insights according to these criteria:

ANALYSIS FOCUS:
- Main themes and topics
- Supporting evidence and data points
- Conclusions and recommendations
- Quality indicators and confidence levels

EXTRACTION REQUIREMENTS:
- Quote relevant passages with page references
- Identify quantitative data with context
- Note any contradictions or inconsistencies
- Highlight actionable insights

OUTPUT FORMAT:
- Structured JSON following the provided schema
- Confidence scores for each insight (1-10 scale)
- Source citations for all extracted information
- Quality flags for uncertain or incomplete data
```

### Classification Tasks

**Pattern**: Examine → Categorize → Justify → Confidence

```
Classify the provided content into the appropriate categories based on these criteria:

CLASSIFICATION CRITERIA:
[Specific criteria for each category]

PROCESS:
1. Read the entire content carefully
2. Identify key indicators for each possible category
3. Apply classification rules in order of priority
4. Assign confidence score to final classification

REQUIRED OUTPUT:
- Primary category assignment
- Confidence level (percentage)
- Key indicators that led to classification
- Alternative categories considered (if any)

EDGE CASES:
- If content fits multiple categories, choose the most dominant
- If content doesn't clearly fit any category, use "Other" with explanation
- If content quality is too poor to classify, flag as "Unclassifiable"
```

### Content Generation Tasks

**Pattern**: Understand → Plan → Generate → Refine

```
Generate content based on the provided requirements and source materials:

CONTENT REQUIREMENTS:
- Target audience: [specific audience]
- Tone and style: [specific tone]
- Length: [word count or format constraints]
- Key messages: [essential points to convey]

GENERATION PROCESS:
1. Analyze source materials for key information
2. Structure content with clear introduction, body, and conclusion
3. Ensure all key messages are addressed
4. Maintain consistent tone throughout
5. Verify accuracy of all factual claims

QUALITY STANDARDS:
- Factual accuracy verified against source materials
- Appropriate tone for target audience
- Clear, engaging writing style
- Proper grammar and formatting
- Complete coverage of required topics
```

### Data Extraction Tasks

**Pattern**: Locate → Extract → Validate → Format

```
Extract specific data points from the provided documents:

EXTRACTION TARGETS:
[Specific list of data points to find]

EXTRACTION PROCESS:
1. Scan document for relevant sections
2. Locate specific data points using provided patterns
3. Extract data with surrounding context
4. Validate extracted data for completeness and accuracy
5. Format according to schema requirements

DATA QUALITY REQUIREMENTS:
- Exact values, not approximations
- Include source page/section references
- Flag any uncertain or incomplete extractions
- Maintain original formatting where relevant

ERROR HANDLING:
- If data point not found: explicitly state "Not Found"
- If data is ambiguous: provide all possible interpretations
- If data quality is poor: flag as "Low Confidence"
```

## Schema-Based Prompts

### JSON Schema Integration

When working with structured outputs:

```
RESPONSE FORMAT: JSON only, following this exact schema structure.

SCHEMA REQUIREMENTS:
- All required fields must be present
- Use null for missing optional values
- Ensure data types match schema specifications
- Validate array elements match expected format

EXAMPLE OUTPUT:
```json
{
  "field1": "example value",
  "field2": 123,
  "field3": ["item1", "item2"],
  "field4": null
}
```

VALIDATION CHECKLIST:
□ All required fields included
□ Correct data types used
□ Arrays contain valid elements
□ No extra fields outside schema
□ Null used appropriately for missing data
```

### Complex Schema Handling

For nested or complex schemas:

```
SCHEMA STRUCTURE EXPLANATION:
- Root level contains [list main sections]
- Each section includes [describe structure]
- Relationships between sections: [explain connections]

POPULATION STRATEGY:
1. Process input data section by section
2. Map input elements to schema fields
3. Resolve relationships and references
4. Validate completeness before output

QUALITY ASSURANCE:
- Verify all relationships are valid
- Ensure no circular references
- Confirm all required nested objects are complete
- Validate array contents match expectations
```

## Error Prevention Strategies

### Common Pitfalls and Solutions

**Ambiguous Instructions**
- Problem: Vague or interpretable requirements
- Solution: Use specific, measurable criteria
- Example: "Summarize briefly" → "Create 3-sentence summary focusing on key outcomes"

**Missing Context**
- Problem: Agent lacks necessary background information
- Solution: Include relevant context in prompt
- Example: Add industry context, technical definitions, or process background

**Format Confusion**
- Problem: Output doesn't match expected format
- Solution: Provide explicit format examples
- Example: Include sample outputs with exact formatting requirements

**Edge Case Handling**
- Problem: Unexpected inputs cause failures
- Solution: Explicitly address edge cases
- Example: Instructions for empty inputs, malformed data, or missing information

### Validation Integration

```
BUILT-IN VALIDATION:
Before providing final output, verify:
□ All requirements have been addressed
□ Output format matches specification exactly
□ Quality criteria have been met
□ No information has been fabricated or assumed

SELF-CHECK PROCESS:
1. Re-read the original requirements
2. Compare output against each requirement
3. Verify all examples and criteria are followed
4. Confirm output completeness and accuracy

ERROR RECOVERY:
If any validation check fails:
- Identify the specific issue
- Revise output to address the problem
- Re-run validation before final submission
```

## Quality Optimization Techniques

### Iterative Refinement

**Multi-Step Processing**
```
STEP 1: Initial Analysis
[First pass processing instructions]

STEP 2: Quality Review
[Self-review and improvement instructions]

STEP 3: Final Validation
[Final check against all requirements]

Provide output from Step 3 only, but use all steps in your thinking process.
```

### Confidence Scoring

```
CONFIDENCE ASSESSMENT:
For each major output element, assess confidence level:
- High (90-100%): Certain based on clear evidence
- Medium (70-89%): Reasonably certain with good evidence
- Low (50-69%): Uncertain or based on limited evidence
- Very Low (<50%): Highly uncertain or speculative

CONFIDENCE REPORTING:
Include confidence scores in output and explain reasoning for low-confidence items.
```

### Quality Checkpoints

```
QUALITY GATES:
Gate 1: Completeness
- All required elements present
- No missing information

Gate 2: Accuracy  
- Factual information verified
- No contradictions or errors

Gate 3: Format Compliance
- Output matches specified format exactly
- Schema validation passes (if applicable)

Gate 4: Relevance
- Output addresses the stated objective
- No off-topic or unnecessary content

Only proceed to final output if all gates pass.
```

## Prompt Optimization Best Practices

### Language and Style

**Use Active Voice**
- "Analyze the document" not "The document should be analyzed"
- Direct, clear instructions
- Specific action verbs

**Be Concise Yet Complete**
- Remove unnecessary words
- Include all essential information
- Use bullet points for clarity
- Structure information logically

**Maintain Consistency**
- Use same terminology throughout
- Consistent formatting style
- Parallel structure for similar instructions

### Testing and Iteration

**Prompt Testing Process**
1. Test with typical inputs
2. Test with edge cases
3. Test with poor quality inputs
4. Validate outputs against requirements
5. Refine based on results

**Performance Monitoring**
- Track success rates across different input types
- Monitor output quality consistency
- Identify common failure patterns
- Gather user feedback on results

**Continuous Improvement**
- Regular prompt review and updates
- Incorporate lessons learned from failures
- Adapt to new requirements or edge cases
- Optimize for better performance

## Advanced Techniques

### Chain-of-Thought Prompting

```
REASONING PROCESS:
Show your step-by-step thinking:

1. UNDERSTANDING: What am I being asked to do?
2. ANALYSIS: What are the key elements in the input?
3. PROCESSING: How do I transform input to output?
4. VALIDATION: Does my output meet all requirements?
5. CONFIDENCE: How certain am I about this result?

Provide your reasoning for each step, then give the final output.
```

### Few-Shot Learning

```
EXAMPLES:

Example 1:
Input: [sample input 1]
Process: [thinking process]
Output: [expected output 1]

Example 2:
Input: [sample input 2]
Process: [thinking process]
Output: [expected output 2]

Now process this new input following the same pattern:
Input: [actual input]
```

### Multi-Modal Instructions

For tasks involving different input types:

```
INPUT PROCESSING BY TYPE:

TEXT CONTENT:
- Extract and analyze textual information
- Apply NLP techniques for understanding
- Consider context and meaning

IMAGE CONTENT:
- Describe visual elements relevant to task
- Extract text via OCR if needed
- Identify patterns or structures

STRUCTURED DATA:
- Parse according to format specifications
- Validate data integrity
- Extract relevant fields and relationships

INTEGRATION:
Combine insights from all input types to create comprehensive output.
```

This guide provides the foundation for creating effective prompts that leverage AI capabilities while ensuring consistent, high-quality workflow outputs.