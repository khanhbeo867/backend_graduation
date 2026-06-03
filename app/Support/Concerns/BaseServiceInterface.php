<?php

namespace App\Support\Concerns;

interface BaseServiceInterface {
    public function all(array $relations = []): array;
    public function find(int $id, array $relations = []): array;
    public function create(array $data): array;
    public function update(array $data, int $id): array;
    public function delete(int $id): ?array;
}
