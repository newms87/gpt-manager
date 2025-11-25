<?php

namespace App\Services\Testing;

use RuntimeException;

class TestLockService
{
    private const string LOCK_FILE_PATH = '/tmp/gpt-manager-tests.lock';

    private mixed $lockFileHandle = null;

    /**
     * Acquires an exclusive lock for running tests
     *
     * @throws RuntimeException If the lock cannot be acquired
     */
    public function acquireLock(): void
    {
        // Try to open/create the lock file
        $this->lockFileHandle = fopen(self::LOCK_FILE_PATH, 'c+');

        if ($this->lockFileHandle === false) {
            throw new RuntimeException('Failed to open lock file: ' . self::LOCK_FILE_PATH);
        }

        // Try to acquire an exclusive, non-blocking lock
        if (!flock($this->lockFileHandle, LOCK_EX | LOCK_NB)) {
            // Could not acquire lock - another test is running
            $existingLockInfo = $this->readLockInfo();
            fclose($this->lockFileHandle);
            $this->lockFileHandle = null;

            $message = $this->buildLockErrorMessage($existingLockInfo);
            throw new RuntimeException($message);
        }

        // Lock acquired - write our process information
        $this->writeLockInfo();
    }

    /**
     * Releases the test lock
     */
    public function releaseLock(): void
    {
        if ($this->lockFileHandle === null) {
            return;
        }

        // Release the lock
        flock($this->lockFileHandle, LOCK_UN);
        fclose($this->lockFileHandle);
        $this->lockFileHandle = null;

        // Remove the lock file
        if (file_exists(self::LOCK_FILE_PATH)) {
            unlink(self::LOCK_FILE_PATH);
        }
    }

    /**
     * Reads the lock information from the lock file
     */
    private function readLockInfo(): ?array
    {
        if (!file_exists(self::LOCK_FILE_PATH)) {
            return null;
        }

        $content = file_get_contents(self::LOCK_FILE_PATH);
        if ($content === false) {
            return null;
        }

        $info = json_decode($content, true);
        if (!is_array($info)) {
            return null;
        }

        // Check if the process is still running
        if (isset($info['pid']) && !$this->isProcessRunning($info['pid'])) {
            // Process is dead - remove stale lock
            unlink(self::LOCK_FILE_PATH);

            return null;
        }

        return $info;
    }

    /**
     * Writes the current process information to the lock file
     */
    private function writeLockInfo(): void
    {
        if ($this->lockFileHandle === null) {
            return;
        }

        $info = [
            'pid'        => getmypid(),
            'user'       => get_current_user(),
            'started_at' => date('Y-m-d H:i:s'),
        ];

        ftruncate($this->lockFileHandle, 0);
        rewind($this->lockFileHandle);
        fwrite($this->lockFileHandle, json_encode($info, JSON_PRETTY_PRINT));
        fflush($this->lockFileHandle);
    }

    /**
     * Checks if a process is still running
     */
    private function isProcessRunning(int $pid): bool
    {
        // On Unix-like systems, sending signal 0 checks if process exists
        return posix_kill($pid, 0);
    }

    /**
     * Builds a descriptive error message for when lock cannot be acquired
     */
    private function buildLockErrorMessage(?array $lockInfo): string
    {
        if ($lockInfo === null) {
            return 'Cannot run tests: Another test suite is currently running. Please wait for it to complete.';
        }

        $pid       = $lockInfo['pid']              ?? 'unknown';
        $user      = $lockInfo['user']            ?? 'unknown';
        $startedAt = $lockInfo['started_at'] ?? 'unknown';

        return sprintf(
            'Cannot run tests: Another test suite is currently running (PID: %s, User: %s, Started: %s). Please wait for it to complete.',
            $pid,
            $user,
            $startedAt
        );
    }

    /**
     * Returns the lock file handle for testing purposes
     */
    public function getLockFileHandle(): mixed
    {
        return $this->lockFileHandle;
    }
}
