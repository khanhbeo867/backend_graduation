<?php

namespace App\Support\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

abstract class BaseService implements BaseServiceInterface
{
    protected Model $model;
    protected array $relations = [];
    protected array $select = [];

    public function __construct(Model $model, array $relations = [], array $select = [])
    {
        $this->model = $model;
        $this->relations = $relations;
        $this->select = $select;
    }

    public function all(array $relations = []): array
    {
        return $this->newQuery($relations)
            ->get()
            ->toArray();
    }

    public function find(int $id, array $relations = []): array
    {
        return $this->newQuery($relations)
            ->whereKey($id)
            ->firstOrFail()
            ->toArray();
    }

    public function create(array $data): array
    {
        try {
            $record = DB::transaction(fn() => tap($this->model->create($data), function (Model $record) use ($data): void {
                $this->afterCreate($record, $data);
            }));

            return $this->freshRecord($record)->toArray();
        } catch (Throwable $e) {
            Log::error("Error creating record in " . get_class($this) . ": " . $e->getMessage());
            throw $e;
        }
    }

    public function update(array $data, int $id): array
    {
        try {
            $record = DB::transaction(function () use ($data, $id): Model {
                $record = $this->model->newQuery()->findOrFail($id);
                $record->update($data);
                $this->afterUpdate($record, $data);

                return $record;
            });

            return $this->freshRecord($record)->toArray();
        } catch (Throwable $e) {
            Log::error("Error updating record in " . get_class($this) . " (ID: {$id}): " . $e->getMessage());
            throw $e;
        }
    }

    public function delete(int $id): ?array
    {
        try {
            DB::transaction(function () use ($id): void {
                $record = $this->model->newQuery()->findOrFail($id);

                if ($this->usesActiveFlag($record)) {
                    $record->update([
                        'is_active' => false,
                    ]);
                } else {
                    $record->delete();
                }

                $this->afterDelete($record);
            });

            return null;
        } catch (Throwable $e) {
            Log::error("Error deleting record in " . get_class($this) . " (ID: {$id}): " . $e->getMessage());
            throw $e;
        }
    }

    protected function newQuery(array $relations = []): Builder
    {
        $relations = $relations !== [] ? $relations : $this->relations;
        $query = $this->model->newQuery()->with($relations);

        if ($this->select !== []) {
            $query->select($this->select);
        }

        return $query;
    }

    protected function freshRecord(Model $record): Model
    {
        return $this->newQuery()->whereKey($record->getKey())->firstOrFail();
    }

    protected function usesActiveFlag(Model $record): bool
    {
        return in_array('is_active', $record->getFillable(), true)
            || $record->getConnection()
                ->getSchemaBuilder()
                ->hasColumn($record->getTable(), 'is_active');
    }

    protected function afterCreate(Model $record, array $data): void
    {
    }

    protected function afterUpdate(Model $record, array $data): void
    {
    }

    protected function afterDelete(Model $record): void
    {
    }
}
