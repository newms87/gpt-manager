<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class TeamScopedUniqueRule implements ValidationRule
{
    public function __construct(
        private string $table,
        private string $column = 'name',
        private ?Model $ignore = null,
        private array $additionalWhere = []
    ) {}

    /**
     * Run the validation rule
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $query = DB::table($this->table)
            ->where($this->column, $value)
            ->where('team_id', team()->id);

        // Add soft deletes constraint if table has deleted_at column
        if ($this->hasColumn($this->table, 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        // Ignore specific record (for updates)
        if ($this->ignore) {
            $query->where('id', '!=', $this->ignore->id);
        }

        // Add additional where constraints
        foreach ($this->additionalWhere as $column => $value) {
            if ($value === null) {
                $query->whereNull($column);
            } else {
                $query->where($column, $value);
            }
        }

        if ($query->exists()) {
            $fail("The {$attribute} has already been taken for this team.");
        }
    }

    /**
     * Check if a table has a specific column
     */
    private function hasColumn(string $table, string $column): bool
    {
        return DB::getSchemaBuilder()->hasColumn($table, $column);
    }

    /**
     * Static factory method for fluent usage
     */
    public static function make(string $table, string $column = 'name'): self
    {
        return new self($table, $column);
    }

    /**
     * Set record to ignore during validation (for updates)
     */
    public function ignore(?Model $model): self
    {
        $this->ignore = $model;
        return $this;
    }

    /**
     * Add additional where constraints
     */
    public function where(array $conditions): self
    {
        $this->additionalWhere = array_merge($this->additionalWhere, $conditions);
        return $this;
    }
}