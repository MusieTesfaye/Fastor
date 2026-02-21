<?php

namespace Fastor\Database;

use Cycle\ORM\Select;

class Query
{
    private Select $select;

    public function __construct(Select $select)
    {
        $this->select = $select;
    }

    /**
     * Automatically apply pagination and sorting from the current request.
     * Looks for 'limit', 'skip', 'offset', and 'sort' query parameters.
     */
    public function autoPaginate(?int $limit = null, ?int $skip = null, ?string $sort = null, string $defaultSort = '-id'): self
    {
        $request = request();
        
        $limit = $limit ?? (int)$request->query('limit', 10);
        $offset = $skip ?? (int)$request->query('skip', (int)$request->query('offset', 0));
        $sortQuery = $sort ?? (string)$request->query('sort', $defaultSort);

        $this->select->limit($limit);
        $this->select->offset($offset);

        // Parse sort string, e.g., "-created_at,name"
        if (!empty($sortQuery)) {
            $sortFields = explode(',', $sortQuery);
            foreach ($sortFields as $field) {
                $field = trim($field);
                if (empty($field)) continue;

                $direction = 'ASC';
                if ($field[0] === '-') {
                    $direction = 'DESC';
                    $field = substr($field, 1);
                }
                
                $this->select->orderBy($field, $direction);
            }
        }

        return $this;
    }

    public function limit(int $limit): self
    {
        $this->select->limit($limit);
        return $this;
    }

    public function offset(int $offset): self
    {
        $this->select->offset($offset);
        return $this;
    }

    public function where(array|string|callable $where, ...$parameters): self
    {
        $this->select->where($where, ...$parameters);
        return $this;
    }

    public function fetchAll(): array
    {
        return $this->select->fetchAll();
    }

    public function fetchOne(): ?object
    {
        return $this->select->fetchOne();
    }

    public function __call(string $name, array $arguments)
    {
        return $this->select->$name(...$arguments);
    }
}
