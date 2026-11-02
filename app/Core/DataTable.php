<?php
namespace App\Core;

/** Server-side search, filter, sort and pagination for admin lists. */
class DataTable
{
    public array $rows = [];
    public int $total = 0;
    public int $page = 1;
    public int $perPage = 20;
    public string $search = '';

    /**
     * spec keys: select, from, where (SQL), params, search (columns), filters, order,
     * columns (each may declare 'sort' => column).
     */
    public function __construct(public array $spec)
    {
        $this->run();
    }

    private function run(): void
    {
        $s = $this->spec;
        $where = [$s['where'] ?? '1=1'];
        $params = $s['params'] ?? [];

        $this->search = (string) Request::query('q', '');
        if ($this->search !== '' && !empty($s['search'])) {
            $like = '%' . addcslashes($this->search, '%_\\') . '%';
            $where[] = '(' . implode(' OR ', array_map(fn ($c) => "$c LIKE ?", $s['search'])) . ')';
            array_push($params, ...array_fill(0, count($s['search']), $like));
        }

        foreach ($s['filters'] ?? [] as $f) {
            $v = Request::query($f['name']);
            if ($v === null || $v === '') {
                continue;
            }
            $op = match ($f['type'] ?? 'select') {
                'date_from' => '>=',
                'date_to' => '<=',
                default => '=',
            };
            $where[] = "{$f['column']} $op ?";
            $params[] = $v;
        }

        $whereSql = implode(' AND ', $where);
        $this->total = (int) Database::value("SELECT COUNT(*) FROM {$s['from']} WHERE $whereSql", $params);
        $this->perPage = $s['per_page'] ?? 20;
        $this->page = max(1, Request::int('page', 1));

        $order = $s['order'] ?? 't.id DESC';
        $sort = Request::query('sort');
        foreach ($s['columns'] ?? [] as $c) {
            if (!empty($c['sort']) && ($c['key'] ?? null) === $sort) {
                $order = $c['sort'] . (Request::query('dir') === 'asc' ? ' ASC' : ' DESC');
            }
        }

        $offset = ($this->page - 1) * $this->perPage;
        $this->rows = Database::all(
            "SELECT {$s['select']} FROM {$s['from']} WHERE $whereSql ORDER BY $order LIMIT {$this->perPage} OFFSET $offset",
            $params
        );
    }

    public function pages(): int
    {
        return (int) max(1, ceil($this->total / $this->perPage));
    }
}
