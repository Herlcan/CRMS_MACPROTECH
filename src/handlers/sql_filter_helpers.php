<?php

if (!function_exists('sql_filter_new')) {
    function sql_filter_new(string $baseClause = '1'): array {
        return [
            'clauses' => [$baseClause],
            'types' => '',
            'params' => []
        ];
    }
}

if (!function_exists('sql_filter_validate_clause')) {
    function sql_filter_validate_clause(string $clause, string $types, array $params): void {
        $clause = trim($clause);

        if ($clause === '') {
            throw new InvalidArgumentException('SQL filter clause cannot be empty.');
        }

        if (preg_match('/;|--|#|\/\*|\*\//', $clause)) {
            throw new InvalidArgumentException('SQL filter clause contains unsafe syntax.');
        }

        if (substr_count($clause, '?') !== strlen($types) || strlen($types) !== count($params)) {
            throw new InvalidArgumentException('SQL filter placeholders do not match bound parameters.');
        }
    }
}

if (!function_exists('sql_filter_identifier')) {
    function sql_filter_identifier(string $identifier): string {
        $identifier = trim($identifier);

        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*(?:\.[A-Za-z_][A-Za-z0-9_]*)?$/', $identifier)) {
            throw new InvalidArgumentException('Invalid SQL identifier.');
        }

        return $identifier;
    }
}

if (!function_exists('sql_filter_add_condition')) {
    function sql_filter_add_condition(array &$filter, string $clause, string $types = '', array $params = []): void {
        sql_filter_validate_clause($clause, $types, $params);

        $filter['clauses'][] = trim($clause);
        $filter['types'] .= $types;
        foreach ($params as $param) {
            $filter['params'][] = $param;
        }
    }
}

if (!function_exists('sql_filter_add_like_any')) {
    function sql_filter_add_like_any(array &$filter, array $columns, string $term): void {
        $term = trim($term);

        if ($term === '' || empty($columns)) {
            return;
        }

        $like = '%' . strtolower($term) . '%';
        $parts = [];
        $params = [];

        foreach ($columns as $column) {
            $identifier = sql_filter_identifier((string) $column);
            $parts[] = "LOWER({$identifier}) LIKE ?";
            $params[] = $like;
        }

        sql_filter_add_condition($filter, '(' . implode(' OR ', $parts) . ')', str_repeat('s', count($params)), $params);
    }
}

if (!function_exists('sql_filter_add_equals')) {
    function sql_filter_add_equals(array &$filter, string $column, $value, string $type = 's'): void {
        $identifier = sql_filter_identifier($column);
        sql_filter_add_condition($filter, "{$identifier} = ?", $type, [$value]);
    }
}

if (!function_exists('sql_filter_where')) {
    function sql_filter_where(array $filter): string {
        $clauses = array_filter(array_map('trim', $filter['clauses'] ?? []));
        return $clauses ? implode(' AND ', $clauses) : '1';
    }
}

if (!function_exists('sql_filter_types')) {
    function sql_filter_types(array $filter): string {
        return (string) ($filter['types'] ?? '');
    }
}

if (!function_exists('sql_filter_params')) {
    function sql_filter_params(array $filter): array {
        return $filter['params'] ?? [];
    }
}

if (!function_exists('sql_allowed_fragment')) {
    function sql_allowed_fragment(string $key, array $allowedFragments, string $defaultKey): string {
        if (array_key_exists($key, $allowedFragments)) {
            return $allowedFragments[$key];
        }

        if (!array_key_exists($defaultKey, $allowedFragments)) {
            throw new InvalidArgumentException('Default SQL fragment is not defined.');
        }

        return $allowedFragments[$defaultKey];
    }
}

?>
