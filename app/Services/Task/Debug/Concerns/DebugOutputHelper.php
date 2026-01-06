<?php

namespace App\Services\Task\Debug\Concerns;

use App\Models\Task\TaskRun;
use Illuminate\Console\Command;

/**
 * Provides common output formatting utilities for debug services.
 *
 * Extracts reusable patterns for truncating content, indenting multiline
 * output, and displaying standard TaskRun headers.
 */
trait DebugOutputHelper
{
    /**
     * Truncate content with a suffix indicator.
     */
    protected function truncate(string $content, int $maxLength = 1000, string $suffix = '... [truncated]'): string
    {
        if (strlen($content) <= $maxLength) {
            return $content;
        }

        return substr($content, 0, $maxLength) . $suffix;
    }

    /**
     * Indent multiline content by prepending spaces to each line.
     */
    protected function indentContent(string $content, int $spaces = 4): string
    {
        $indent = str_repeat(' ', $spaces);

        return $indent . str_replace("\n", "\n" . $indent, $content);
    }

    /**
     * Display a standard TaskRun header with status and definition info.
     */
    protected function showTaskRunHeader(TaskRun $taskRun, Command $command): void
    {
        $command->info("=== TaskRun {$taskRun->id} ===");
        $command->line("Status: {$taskRun->status}");
        $command->line("TaskDefinition: {$taskRun->taskDefinition->name}");
        $command->line("Runner: {$taskRun->taskDefinition->task_runner_name}");
        $command->newLine();
    }

    /**
     * Format and display JSON content with optional truncation and indentation.
     */
    protected function showJsonContent(
        mixed $data,
        Command $command,
        int $maxLength = 1000,
        int $indent = 4
    ): void {
        $jsonContent = json_encode($data, JSON_PRETTY_PRINT);
        $truncated   = $this->truncate($jsonContent, $maxLength);
        $indented    = $this->indentContent($truncated, $indent);
        $command->line($indented);
    }
}
