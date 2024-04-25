<?php

namespace App\Repositories;

use Exception;
use Flytedan\DanxLaravel\Exceptions\ValidationError;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

abstract class ActionRepository
{
    /** @var string|Model */
    public static string $model;

    /**
     * @return Model
     * @throws Exception
     */
    public function model(): Model
    {
        if (!isset(static::$model)) {
            throw new Exception('$model static property must be set on ' . static::class);
        }

        return new static::$model;
    }

    public function instance($id)
    {
        return $this->model()->find($id);
    }

    public function query(): Builder
    {
        return $this->model()->query();
    }

    public function listQuery(): Builder
    {
        return $this->query();
    }

    public function summary($filter = [])
    {
        return $this->query()->select([
            DB::raw('COUNT(*) as count'),
        ])
            ->filter($filter)
            ->getQuery()
            ->first();
    }

    /**
     * The dynamic and / or static list of options for the filterable fields for the model table
     *
     * @return array
     */
    public function filterFieldOptions($filter = [])
    {
        return [];
    }

    public function applyAction($action, $model, $data)
    {
        // Handle the action
        return match ($action) {
            'update' => $model->update($data),
            default => throw new ValidationError("Invalid action: " . $action)
        };
    }

    /**
     * @param $filter
     * @param $action
     * @param $data
     * @return array
     */
    public function batchAction($filter, $action, $data = [])
    {
        $items = $this->query()->filter($filter)->get();

        $errors = [];

        foreach($items as $item) {
            try {
                $this->applyAction($action, $item, $data);
            } catch(Exception $e) {
                $errors[] = [
                    'id'      => $item->id,
                    'message' => ($item->ref ?? $item->id) . ": " . $e->getMessage(),
                ];
            }
        }

        return $errors;
    }

    /**
     * @param array $filter
     * @return array
     */
    public function export($filter = [])
    {
        return $this->query()
            ->filter($filter)
            ->get()
            ->toArray();
    }
}
