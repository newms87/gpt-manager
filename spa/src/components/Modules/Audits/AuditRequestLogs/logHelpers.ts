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
 * Color palette for hash-based color assignment
 */
export interface ColorConfig {
	bg: string;
	text: string;
}

export const COLOR_PALETTE: ColorConfig[] = [
	{ bg: 'bg-sky-950', text: 'text-sky-400' },
	{ bg: 'bg-blue-950', text: 'text-blue-400' },
	{ bg: 'bg-indigo-950', text: 'text-indigo-400' },
	{ bg: 'bg-purple-950', text: 'text-purple-400' },
	{ bg: 'bg-pink-950', text: 'text-pink-400' },
	{ bg: 'bg-rose-950', text: 'text-rose-400' },
	{ bg: 'bg-orange-950', text: 'text-orange-400' },
	{ bg: 'bg-amber-950', text: 'text-amber-400' },
	{ bg: 'bg-yellow-950', text: 'text-yellow-400' },
	{ bg: 'bg-lime-950', text: 'text-lime-400' },
	{ bg: 'bg-green-950', text: 'text-green-400' },
	{ bg: 'bg-emerald-950', text: 'text-emerald-400' },
	{ bg: 'bg-teal-950', text: 'text-teal-400' },
	{ bg: 'bg-cyan-950', text: 'text-cyan-400' }
];

/**
 * Simple hash function for deterministic color assignment
 */
function hashString(str: string): number {
	let hash = 0;
	for (let i = 0; i < str.length; i++) {
		const char = str.charCodeAt(i);
		hash = ((hash << 5) - hash) + char;
		hash = hash & hash; // Convert to 32-bit integer
	}
	return Math.abs(hash);
}

/**
 * Get a color from the palette based on content hash
 * Same content will always get the same color
 */
export function getHashedColor(content: string): ColorConfig {
	const hash = hashString(content);
	const index = hash % COLOR_PALETTE.length;
	return COLOR_PALETTE[index];
}
