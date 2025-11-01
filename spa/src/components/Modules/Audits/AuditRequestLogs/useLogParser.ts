/**
 * useLogParser - Composable for parsing log lines and embedded objects
 */

import { computed, Ref } from 'vue';
import { fDateTime } from 'quasar-ui-danx';
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
	jobEntry?: JobLogEntry; // Parsed job log entry if this is a job log line
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
		jobEntry: jobEntry || undefined
	};
}

/**
 * Composable for log parsing functionality
 */
export function useLogParser(logs: Ref<string | null | undefined>) {
	const parsedLines = computed<ParsedLogLine[]>(() => {
		if (!logs.value) return [];

		const lines = logs.value.split('\n');
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
		findLockOperations
	};
}
