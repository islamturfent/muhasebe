<?php

declare(strict_types=1);

namespace Muh\Core;

/**
 * Base active-record-ish model providing common query helpers on top of DB.
 *
 * Concrete models declare the table name and timestamps/soft-delete flags.
 * All tenant-scoped models must filter by tenant_id in their queries (see
 * the TenantScope trait in app/Models).
 */
abstract class Model
{
    protected string $table;
    protected string $primaryKey = 'id';
    protected bool $timestamps = true;
    protected bool $softDeletes = false;
    public array $attributes = [];
    private bool $exists = false;

    public function __construct(array $attributes = [])
    {
        $this->fill($attributes);
    }

    public function getTable(): string
    {
        return $this->table;
    }

    public function fill(array $attributes): void
    {
        $this->attributes = array_merge($this->attributes, $attributes);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function toArray(): array
    {
        return $this->attributes;
    }

    public function getId(): ?int
    {
        return isset($this->attributes[$this->primaryKey]) ? (int) $this->attributes[$this->primaryKey] : null;
    }

    // ------------------------------------------------------------------
    // Query helpers
    // ------------------------------------------------------------------

    public static function table(): string
    {
        return (new static())->getTable();
    }

    /** Find by primary key. Respects soft deletes. */
    public static function find(int|string $id): ?static
    {
        $instance = new static();
        $sql = 'SELECT * FROM ' . DB::quoteIdentifier($instance->getTable()) .
               ' WHERE ' . DB::quoteIdentifier($instance->primaryKey) . ' = :id';
        if ($instance->softDeletes) {
            $sql .= ' AND deleted_at IS NULL';
        }
        $row = DB::first($sql, ['id' => $id]);
        if (!$row) {
            return null;
        }
        $instance->attributes = $row;
        $instance->exists = true;
        return $instance;
    }

    public static function where(string $column, mixed $value): static
    {
        $instance = new static();
        $instance->attributes['_where'] = [$column => $value];
        return $instance;
    }

    public static function whereAll(array $conditions): array
    {
        $instance = new static();
        return $instance->queryWhereAll($conditions);
    }

    public function queryWhereAll(array $conditions): array
    {
        $where = [];
        $params = [];
        foreach ($conditions as $col => $value) {
            $where[] = DB::quoteIdentifier($col) . ' = :w_' . $col;
            $params['w_' . $col] = $value;
        }
        $sql = 'SELECT * FROM ' . DB::quoteIdentifier($this->getTable());
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        if ($this->softDeletes) {
            $sql .= ($where ? ' AND' : ' WHERE') . ' deleted_at IS NULL';
        }
        $sql .= ' ORDER BY ' . DB::quoteIdentifier($this->primaryKey) . ' DESC';
        return DB::select($sql, $params);
    }

    public function save(): bool
    {
        $this->attributes[$this->primaryKey] = $this->getId();
        if ($this->timestamps) {
            $this->attributes['updated_at'] = now();
        }

        if ($this->exists && $this->getId()) {
            return DB::update(
                $this->getTable(),
                $this->attributes,
                DB::quoteIdentifier($this->primaryKey) . ' = :pk',
                ['pk' => $this->getId()]
            ) >= 0;
        }

        if ($this->timestamps && empty($this->attributes['created_at'])) {
            $this->attributes['created_at'] = now();
        }
        $id = DB::insert($this->getTable(), $this->attributes);
        $this->attributes[$this->primaryKey] = $id;
        $this->exists = true;
        return true;
    }

    public function delete(): bool
    {
        if (!$this->getId()) {
            return false;
        }
        if ($this->softDeletes) {
            return DB::update(
                $this->getTable(),
                ['deleted_at' => now()],
                DB::quoteIdentifier($this->primaryKey) . ' = :pk',
                ['pk' => $this->getId()]
            ) > 0;
        }
        return DB::delete(
            $this->getTable(),
            DB::quoteIdentifier($this->primaryKey) . ' = :pk',
            ['pk' => $this->getId()]
        ) > 0;
    }

    public function exists(): bool
    {
        return $this->exists;
    }
}
