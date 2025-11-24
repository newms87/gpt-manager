/**
 * Log Helpers - Utility functions for log parsing, filtering, and export
 */

export type LogLevel = 'DEBUG' | 'INFO' | 'WARNING' | 'ERROR' | 'SUCCESS';

export interface LogLevelConfig {
	color: 'slate' | 'blue' | 'amber' | 'red' | 'green';
	bgClass: string;
	textClass: string;
}

/**
 * Log level configuration mapping
 */
export const LOG_LEVEL_CONFIGS: Record<LogLevel, LogLevelConfig> = {
	DEBUG: {
		color: 'slate',
		bgClass: 'bg-slate-950',
		textClass: 'text-slate-400'
	},
	INFO: {
		color: 'blue',
		bgClass: 'bg-blue-950',
		textClass: 'text-blue-400'
	},
	WARNING: {
		color: 'amber',
		bgClass: 'bg-amber-950',
		textClass: 'text-amber-400'
	},
	ERROR: {
		color: 'red',
		bgClass: 'bg-red-950',
		textClass: 'text-red-400'
	},
	SUCCESS: {
		color: 'green',
		bgClass: 'bg-green-950',
		textClass: 'text-green-400'
	}
};

/**
 * Get log level configuration
 */
export function getLogLevelConfig(level: LogLevel): LogLevelConfig {
	return LOG_LEVEL_CONFIGS[level];
}

/**
 * Check if a log line contains lock operations
 */
export function isLockEntry(line: string): boolean {
	const lockKeywords = ['ACQUIRED', 'RELEASED', 'WAIT'];
	return lockKeywords.some((keyword) => line.includes(keyword));
}

/**
 * Filter log lines by keyword and selected log levels
 */
export function filterLogLines(
	lines: string[],
	keyword: string,
	selectedLevels: LogLevel[],
	showLocks: boolean = true
): string[] {
	return lines.filter((line) => {
		// Skip empty lines
		if (!line.trim()) return false;

		// Filter lock entries if showLocks is false
		if (!showLocks && isLockEntry(line)) {
			return false;
		}

		// Apply keyword filter
		if (keyword && !line.toLowerCase().includes(keyword.toLowerCase())) {
			return false;
		}

		// Apply log level filter
		if (selectedLevels.length > 0) {
			const hasMatchingLevel = selectedLevels.some((level) =>
				line.includes(` ${level} `)
			);
			if (!hasMatchingLevel) return false;
		}

		return true;
	});
}

/**
 * Export logs to a downloadable text file
 */
export function exportLogs(logs: string, filename: string = 'logs.txt'): void {
	const blob = new Blob([logs], { type: 'text/plain' });
	const url = URL.createObjectURL(blob);
	const link = document.createElement('a');
	link.href = url;
	link.download = filename;
	document.body.appendChild(link);
	link.click();
	document.body.removeChild(link);
	URL.revokeObjectURL(url);
}

/**
 * Copy text to clipboard
 */
export async function copyToClipboard(text: string): Promise<boolean> {
	try {
		await navigator.clipboard.writeText(text);
		return true;
	} catch (error) {
		console.error('Failed to copy to clipboard:', error);
		return false;
	}
}

/**
 * Color configuration type for backward compatibility with log components
 * Re-exported from useHashedColor composable
 */
import {
	getHashedColor as getHashedColorUtil,
	type TailwindDarkConfig
} from '@/composables/useHashedColor';

export type ColorConfig = TailwindDarkConfig;

/**
 * Get a hashed color configuration for the given content.
 * This is a wrapper around the composable for backward compatibility.
 * Same content will always get the same color.
 *
 * @param content - The string to hash for color selection
 * @returns ColorConfig with bg and text Tailwind classes
 */
export function getHashedColor(content: string): ColorConfig {
	return getHashedColorUtil(content, { format: 'tailwind-dark' });
}
