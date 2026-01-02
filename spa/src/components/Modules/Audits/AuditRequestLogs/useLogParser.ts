/**
 * useLogParser - Composable for parsing log lines and embedded objects
 */

import { computed, Ref } from 'vue';
import type { LogLevel } from './logHelpers';

export interface EmbeddedObject {
	className: string;
	id: string;
	name?: string;
	attributes: Record<string, string>;
	startIndex: number;
	endIndex: number;
}

export interface SquareBracketEntity {
	content: string;
	startIndex: number;
	endIndex: number;
}

export interface EmbeddedJson {
	content: string;      // The raw JSON string
	parsed: object;       // The parsed JSON object
	startIndex: number;   // Start position in message
	endIndex: number;     // End position in message
}

export interface LockOperation {
	type: 'ACQUIRED' | 'RELEASED' | 'WAIT';
	startIndex: number;
	endIndex: number; // End of the operation keyword (before the colon)
}

export interface JobLogEntry {
	status: 'Handling' | 'Completed' | 'Failed';
	fullJobName: string; // e.g., "App\Jobs\TaskProcessJob"
	jobName: string; // Short name, e.g., "TaskProcessJob"
	jobId: string; // e.g., "2057"
	identifier?: string; // e.g., "task-process:workflow-11:6902adfed3e3b9.91530533"
	timing?: string; // e.g., "App\Jobs\TaskProcessJob: 18 s 725 ms (18 s 820 ms)"
}

export interface ParsedLogLine {
	raw: string;
	date?: string;
	time?: string;
	dateTime?: string; // ISO datetime string for formatting with fDateTime
	level?: LogLevel;
	message: string;
	embeddedObjects: EmbeddedObject[];
	squareBracketEntities: SquareBracketEntity[];
	lockOperations: LockOperation[];
	embeddedJsonObjects: EmbeddedJson[];
	jobEntry?: JobLogEntry; // Parsed job log entry if this is a job log line
}

/**
 * Extract a multi-line JSON block starting at the given line index.
 * Returns the JSON content (single line) and the ending line index.
 */
function extractMultiLineJson(lines: string[], startIndex: number): { content: string; endLineIndex: number } | null {
	let depth = 0;
	const jsonLines: string[] = [];
	let inString = false;
	let escapeNext = false;

	for (let i = startIndex; i < lines.length; i++) {
		const line = lines[i];
		jsonLines.push(line.trim());

		// Count braces/brackets (respecting string context)
		for (const char of line) {
			if (escapeNext) {
				escapeNext = false;
				continue;
			}
			if (char === '\\' && inString) {
				escapeNext = true;
				continue;
			}
			if (char === '"') {
				inString = !inString;
				continue;
			}
			if (inString) continue;

			if (char === '{' || char === '[') depth++;
			if (char === '}' || char === ']') depth--;
		}

		// If depth is 0 and we've seen at least the opening, we're done
		if (depth === 0 && jsonLines.length > 0) {
			const content = jsonLines.join(' ').replace(/\s+/g, ' ').trim();
			// Validate it's actually JSON
			try {
				JSON.parse(content);
				return { content, endLineIndex: i };
			} catch {
				return null;
			}
		}

		// Safety: don't go too far (max 100 lines for a JSON block)
		if (i - startIndex > 100) return null;
	}

	return null;
}

/**
 * Pre-process logs to join multi-line JSON blocks with their preceding log line.
 * Detects JSON blocks that start on their own line and joins them to the previous line.
 */
function preprocessLogs(rawLogs: string): string {
	const lines = rawLogs.split('\n');
	const result: string[] = [];
	let i = 0;

	while (i < lines.length) {
		const line = lines[i];
		const trimmed = line.trim();

		// Check if this line is the start of a JSON block (just "{" or "[")
		if (trimmed === '{' || trimmed === '[') {
			// Try to extract the complete JSON block
			const jsonBlock = extractMultiLineJson(lines, i);
			if (jsonBlock) {
				// Append to previous line if exists, otherwise add as new line
				if (result.length > 0) {
					result[result.length - 1] += ' ' + jsonBlock.content;
				} else {
					result.push(jsonBlock.content);
				}
				i = jsonBlock.endLineIndex + 1;
				continue;
			}
		}

		result.push(line);
		i++;
	}

	return result.join('\n');
}

/**
 * Parse embedded object from log message
 * Format: <ClassName id='X' name='...' attr='...'>
 */
function parseEmbeddedObject(text: string, startIndex: number): EmbeddedObject | null {
	const match = text.match(/^<(\w+)\s+([^>]+)>/);
	if (!match) return null;

	const className = match[1];
	const attributesText = match[2];
	const fullMatch = match[0];

	// Parse attributes
	const attributes: Record<string, string> = {};
	const attrRegex = /(\w+)='([^']*)'/g;
	let attrMatch;

	while ((attrMatch = attrRegex.exec(attributesText)) !== null) {
		attributes[attrMatch[1]] = attrMatch[2];
	}

	return {
		className,
		id: attributes.id || '',
		name: attributes.name,
		attributes,
		startIndex,
		endIndex: startIndex + fullMatch.length
	};
}

/**
 * Find all embedded objects in a message
 */
function findEmbeddedObjects(message: string): EmbeddedObject[] {
	const objects: EmbeddedObject[] = [];
	let currentIndex = 0;

	while (currentIndex < message.length) {
		const nextStart = message.indexOf('<', currentIndex);
		if (nextStart === -1) break;

		const remainingText = message.substring(nextStart);
		const obj = parseEmbeddedObject(remainingText, nextStart);

		if (obj) {
			objects.push(obj);
			currentIndex = obj.endIndex;
		} else {
			currentIndex = nextStart + 1;
		}
	}

	return objects;
}

/**
 * Find all square bracket entities in a message
 * Format: [EntityName]
 * Only matches simple alphanumeric identifiers (letters, numbers, underscores)
 * Does NOT match entries with special characters like colons, backslashes, spaces, etc.
 */
function findSquareBracketEntities(message: string): SquareBracketEntity[] {
	const entities: SquareBracketEntity[] = [];
	const regex = /\[([A-Za-z0-9_]+)\]/g;
	let match;

	while ((match = regex.exec(message)) !== null) {
		entities.push({
			content: match[1],
			startIndex: match.index,
			endIndex: match.index + match[0].length
		});
	}

	return entities;
}

/**
 * Find all lock operations in a message
 * Format: ACQUIRED:, RELEASED:, WAIT:
 */
function findLockOperations(message: string): LockOperation[] {
	const operations: LockOperation[] = [];
	const regex = /\b(ACQUIRED|RELEASED|WAIT):/g;
	let match;

	while ((match = regex.exec(message)) !== null) {
		operations.push({
			type: match[1] as 'ACQUIRED' | 'RELEASED' | 'WAIT',
			startIndex: match.index,
			endIndex: match.index + match[1].length
		});
	}

	return operations;
}

/**
 * Extract a balanced JSON structure starting from a given position
 * Returns the extracted string and end index, or null if not valid
 */
function extractBalancedJson(
	message: string,
	startIndex: number,
	openChar: '{' | '[',
	closeChar: '}' | ']'
): { content: string; endIndex: number } | null {
	let depth = 0;
	let inString = false;
	let escapeNext = false;

	for (let i = startIndex; i < message.length; i++) {
		const char = message[i];

		if (escapeNext) {
			escapeNext = false;
			continue;
		}

		if (char === '\\' && inString) {
			escapeNext = true;
			continue;
		}

		if (char === '"') {
			inString = !inString;
			continue;
		}

		if (inString) continue;

		if (char === openChar) {
			depth++;
		} else if (char === closeChar) {
			depth--;
			if (depth === 0) {
				return {
					content: message.substring(startIndex, i + 1),
					endIndex: i + 1
				};
			}
		}
	}

	return null;
}

/**
 * Check if array content looks like JSON (not just a simple entity name)
 * Returns true if the content has JSON-like structure
 */
function isJsonArrayContent(content: string): boolean {
	const trimmed = content.trim();
	// Check if it contains objects, arrays, or key-value pairs
	// Simple [EntityName] patterns should NOT match
	return (
		trimmed.includes('{') ||
		trimmed.includes(':') ||
		trimmed.includes('"') ||
		/^\s*\[/.test(trimmed)
	);
}

/**
 * Find all embedded JSON objects and arrays in a message
 * Detects JSON objects ({...}) and JSON arrays ([...]) with proper nesting
 * Validates by attempting JSON.parse on extracted content
 */
function findEmbeddedJson(message: string): EmbeddedJson[] {
	const results: EmbeddedJson[] = [];
	let currentIndex = 0;

	while (currentIndex < message.length) {
		// Find next potential JSON start
		let nextObject = message.indexOf('{', currentIndex);
		let nextArray = message.indexOf('[', currentIndex);

		// Skip if neither found
		if (nextObject === -1 && nextArray === -1) break;

		// Determine which comes first
		let startIndex: number;
		let openChar: '{' | '[';
		let closeChar: '}' | ']';

		if (nextObject === -1) {
			startIndex = nextArray;
			openChar = '[';
			closeChar = ']';
		} else if (nextArray === -1) {
			startIndex = nextObject;
			openChar = '{';
			closeChar = '}';
		} else if (nextObject < nextArray) {
			startIndex = nextObject;
			openChar = '{';
			closeChar = '}';
		} else {
			startIndex = nextArray;
			openChar = '[';
			closeChar = ']';
		}

		// Try to extract balanced JSON
		const extracted = extractBalancedJson(message, startIndex, openChar, closeChar);

		if (extracted) {
			// For arrays, check if content looks like JSON (not simple [EntityName])
			if (openChar === '[' && !isJsonArrayContent(extracted.content)) {
				currentIndex = startIndex + 1;
				continue;
			}

			// Try to parse as JSON
			try {
				const parsed = JSON.parse(extracted.content);
				results.push({
					content: extracted.content,
					parsed,
					startIndex,
					endIndex: extracted.endIndex
				});
				currentIndex = extracted.endIndex;
			} catch {
				// Not valid JSON, continue searching
				currentIndex = startIndex + 1;
			}
		} else {
			// Could not extract balanced structure
			currentIndex = startIndex + 1;
		}
	}

	return results;
}

/**
 * Parse job log entry from message
 * Format: ###### Job {Status} {JobName} ({ID}) --- {identifier} --- ({timing})
 * Examples:
 *   ###### Job Handling  App\Jobs\TaskProcessJob (2057) --- task-process:workflow-11:6902adfed3e3b9.91530533
 *   ###### Job Completed App\Jobs\TaskProcessJob (2057) --- task-process:workflow-11:6902adfed3e3b9.91530533 --- ([App\Jobs\TaskProcessJob: 18 s 725 ms (18 s 820 ms)])
 *   ###### Job Failed   App\Jobs\TaskProcessJob (2057) --- ($time)
 */
function parseJobLogEntry(message: string): JobLogEntry | null {
	// Match the job log pattern
	const regex = /^######\s+Job\s+(Handling|Completed|Failed)\s+([^\s(]+)\s*\((\d+)\)\s*(?:---\s*([^\s]+))?(?:\s*---\s*\(\[([^\]]+)\]\))?/;
	const match = message.match(regex);

	if (!match) return null;

	const [, status, fullJobName, jobId, identifier, timing] = match;

	// Extract short job name (last part after backslash)
	const jobNameParts = fullJobName.split('\\');
	const jobName = jobNameParts[jobNameParts.length - 1];

	return {
		status: status as 'Handling' | 'Completed' | 'Failed',
		fullJobName,
		jobName,
		jobId,
		identifier: identifier || undefined,
		timing: timing || undefined
	};
}

/**
 * Parse a single log line
 * Expected format: {date} {time} {level} {message}
 * Example: 2025-10-29 05:06:21 DEBUG appending 0 files
 */
function parseLogLine(line: string): ParsedLogLine {
	const trimmedLine = line.trim();

	// Try to match the standard log format
	const logRegex = /^(\d{4}-\d{2}-\d{2})\s+(\d{2}:\d{2}:\d{2})\s+(DEBUG|INFO|WARNING|ERROR|SUCCESS)\s+(.+)$/;
	const match = trimmedLine.match(logRegex);

	if (match) {
		const [, date, time, level, message] = match;
		const jobEntry = parseJobLogEntry(message);

		return {
			raw: line,
			date,
			time,
			dateTime: `${date} ${time}`, // Combined for formatting with fDateTime
			level: level as LogLevel,
			message,
			embeddedObjects: findEmbeddedObjects(message),
			squareBracketEntities: findSquareBracketEntities(message),
			lockOperations: findLockOperations(message),
			embeddedJsonObjects: findEmbeddedJson(message),
			jobEntry: jobEntry || undefined
		};
	}

	// If not matching standard format, treat entire line as message
	const jobEntry = parseJobLogEntry(trimmedLine);

	return {
		raw: line,
		message: trimmedLine,
		embeddedObjects: findEmbeddedObjects(trimmedLine),
		squareBracketEntities: findSquareBracketEntities(trimmedLine),
		lockOperations: findLockOperations(trimmedLine),
		embeddedJsonObjects: findEmbeddedJson(trimmedLine),
		jobEntry: jobEntry || undefined
	};
}

/**
 * Composable for log parsing functionality
 */
export function useLogParser(logs: Ref<string | null | undefined>) {
	const parsedLines = computed<ParsedLogLine[]>(() => {
		if (!logs.value) return [];

		// Pre-process to join multi-line JSON blocks with preceding log lines
		const preprocessed = preprocessLogs(logs.value);
		const lines = preprocessed.split('\n');
		return lines
			.filter((line) => line.trim()) // Skip empty lines
			.map((line) => parseLogLine(line));
	});

	const logLevels = computed<LogLevel[]>(() => {
		const levels = new Set<LogLevel>();
		parsedLines.value.forEach((line) => {
			if (line.level) {
				levels.add(line.level);
			}
		});
		return Array.from(levels).sort();
	});

	return {
		parsedLines,
		logLevels,
		parseLogLine,
		findEmbeddedObjects,
		findSquareBracketEntities,
		findLockOperations,
		findEmbeddedJson
	};
}
