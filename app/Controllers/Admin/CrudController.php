<?php
namespace App\Controllers\Admin;

use App\Core\Activity;
use App\Core\Auth;
use App\Core\BusinessRuleException;
use App\Core\DataTable;
use App\Core\Database as DB;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Uploader;
use App\Core\ValidationException;
use App\Core\Validator;
use App\Services\Lookup;

/**
 * Generic list, form, detail, delete and cancel handling for the modules in config/resources.php.
 *
 * Resource keys: title, singular, icon, perm, table, soft, select, joins, where, farm_column, search, filters,
 * order, columns, fields, defaults, toolbar, note, actions, readonly, no_edit, no_delete, custom_form, hide_nav,
 * store, update, delete, cancel, cancel_when, cancel_label, before_save, after_save, before_delete, load_extra,
 * show_view, show_data, on_show, delete_check, pk.
 */
class CrudController extends AdminController
{
    private function res(string $slug): array
    {
        $r = config('resources')[$slug] ?? null;
        if (!$r) {
            throw new NotFoundException();
        }
        return $r;
    }

    private function from(array $r): string
    {
        return "`{$r['table']}` t " . ($r['joins'] ?? '');
    }

    private function where(array $r): string
    {
        $w = $r['where'] ?? '1=1';
        if (!empty($r['soft'])) {
            $w .= ' AND t.deleted_at IS NULL';
        }
        if (!empty($r['farm_column'])) {
            $w .= Auth::farmSql($r['farm_column']);
        }
        return $w;
    }

    /** Loads a row through the same scope as the list, so another farm's record reads as not found. */
    private function row(array $r, int $id): array
    {
        $pk = $r['pk'] ?? 'id';
        $select = $r['select'] ?? 't.*';
        $row = DB::one("SELECT $select FROM {$this->from($r)} WHERE {$this->where($r)} AND t.`$pk`=?", [$id]);
        if (!$row) {
            throw new NotFoundException();
        }
        return $row;
    }

    public function index(string $slug): void
    {
        $r = $this->res($slug);
        $columns = array_values(array_filter($r['columns'], fn ($c) => empty($c['perm']) || can($c['perm'])));
        $table = new DataTable([
            'select' => $r['select'] ?? 't.*',
            'from' => $this->from($r),
            'where' => $this->where($r),
            'search' => $r['search'] ?? [],
            'filters' => $r['filters'] ?? [],
            'order' => $r['order'] ?? 't.id DESC',
            'columns' => $columns,
        ]);
        $this->view('admin/crud/index', ['slug' => $slug, 'r' => $r, 'table' => $table, 'columns' => $columns, 'title' => $r['title']]);
    }

    public function create(string $slug): void
    {
        $r = $this->res($slug);
        $this->assertWritable($r, 'create');
        $this->view('admin/crud/form', [
            'slug' => $slug, 'r' => $r, 'row' => [], 'fields' => $this->formFields($r, false),
            'action' => "admin/$slug", 'title' => 'New ' . strtolower($r['singular']),
        ]);
    }

    public function store(string $slug): void
    {
        $r = $this->res($slug);
        $this->assertWritable($r, 'create');
        $fields = $this->formFields($r, false);
        $data = $this->collect($slug, $r, $fields, null);
        $id = DB::transaction(function () use ($r, $data, $slug) {
            if (!empty($r['before_save'])) {
                $data = $r['before_save']($data, null);
            }
            if (!empty($r['store'])) {
                $id = $r['store']($data);
            } else {
                $id = DB::insert($r['table'], $this->columnsOnly($r, $data) + ($r['defaults'] ?? []));
                Activity::log('create', $slug, $id, $data['name'] ?? $data['title'] ?? null);
            }
            if (!empty($r['after_save'])) {
                $r['after_save']($id, $data);
            }
            return $id;
        });
        flash('success', $r['singular'] . ' saved.');
        redirect("admin/$slug");
    }

    public function edit(string $slug, string $id): void
    {
        $r = $this->res($slug);
        $this->assertWritable($r, 'edit');
        $row = $this->row($r, (int) $id);
        if (!empty($r['load_extra'])) {
            $row = $r['load_extra']($row);
        }
        $this->view('admin/crud/form', [
            'slug' => $slug, 'r' => $r, 'row' => $row, 'fields' => $this->formFields($r, true),
            'action' => "admin/$slug/$id", 'title' => 'Edit ' . strtolower($r['singular']),
        ]);
    }

    public function update(string $slug, string $id): void
    {
        $r = $this->res($slug);
        $this->assertWritable($r, 'edit');
        $id = (int) $id;
        $row = $this->row($r, $id);
        $fields = $this->formFields($r, true);
        $data = $this->collect($slug, $r, $fields, $row);
        DB::transaction(function () use ($r, $data, $id, $slug) {
            if (!empty($r['before_save'])) {
                $data = $r['before_save']($data, $id);
            }
            if (!empty($r['update'])) {
                $r['update']($id, $data);
            } else {
                $cols = $this->columnsOnly($r, $data);
                if ($cols) {
                    DB::update($r['table'], $cols, $id);
                }
            }
            if (!empty($r['after_save'])) {
                $r['after_save']($id, $data);
            }
            Activity::log('update', $slug, $id, $data['name'] ?? $data['title'] ?? null);
        });
        flash('success', $r['singular'] . ' updated.');
        redirect("admin/$slug");
    }

    public function show(string $slug, string $id): void
    {
        $r = $this->res($slug);
        $row = $this->row($r, (int) $id);
        if (!empty($r['on_show'])) {
            $r['on_show']($row);
        }
        $extra = !empty($r['show_data']) ? $r['show_data']($row) : [];
        $this->view('admin/crud/show', [
            'slug' => $slug, 'r' => $r, 'row' => $row, 'extra' => $extra, 'title' => $row['name'] ?? $row['title'] ?? $r['singular'],
        ]);
    }

    public function destroy(string $slug, string $id): void
    {
        $r = $this->res($slug);
        if (!empty($r['no_delete']) || (!empty($r['readonly']) && empty($r['can_delete']))) {
            throw new NotFoundException();
        }
        $id = (int) $id;
        $row = $this->row($r, $id);
        if (!empty($r['delete'])) {
            $r['delete']($id);
        } else {
            if (!empty($r['before_delete'])) {
                $r['before_delete']($row);
            }
            foreach ($r['delete_check'] ?? [] as [$table, $col, $label, $soft]) {
                $sql = "SELECT COUNT(*) FROM `$table` WHERE `$col`=?" . ($soft ? ' AND deleted_at IS NULL' : '');
                if (DB::value($sql, [$id])) {
                    throw new BusinessRuleException("This {$r['singular']} is used by $label, so it cannot be deleted. Mark it inactive instead if you no longer need it.");
                }
            }
            if (!empty($r['soft'])) {
                DB::update($r['table'], ['deleted_at' => date('Y-m-d H:i:s')], $id);
            } else {
                DB::query("DELETE FROM `{$r['table']}` WHERE id=?", [$id]);
            }
            Activity::log('delete', $slug, $id, $row['name'] ?? $row['title'] ?? null);
        }
        flash('success', $r['singular'] . ' deleted.');
        redirect("admin/$slug");
    }

    public function cancel(string $slug, string $id): void
    {
        $r = $this->res($slug);
        if (empty($r['cancel'])) {
            throw new NotFoundException();
        }
        $this->row($r, (int) $id);
        $reason = mb_substr((string) Request::input('reason', ''), 0, 255) ?: null;
        $r['cancel']((int) $id, $reason);
        flash('success', $r['singular'] . ' ' . strtolower(($r['cancel_label'] ?? 'Cancel') === 'Void' ? 'voided' : 'cancelled') . '. Its stock and money effects were reversed.');
        $back = $_SERVER['HTTP_REFERER'] ?? '';
        redirect(str_contains($back, "/admin/$slug/$id") ? "admin/$slug/$id" : "admin/$slug");
    }

    private function assertWritable(array $r, string $mode): void
    {
        if (!empty($r['readonly']) || !empty($r['custom_form']) || ($mode === 'edit' && !empty($r['no_edit']))) {
            throw new NotFoundException();
        }
    }

    private function formFields(array $r, bool $editing): array
    {
        return array_values(array_filter($r['fields'], fn ($f) => !($editing && !empty($f['create_only'])) && !(!$editing && !empty($f['edit_only']))));
    }

    /** Builds validation from the field list, checks select values against what this user may choose, and stores uploads. */
    private function collect(string $slug, array $r, array $fields, ?array $row): array
    {
        $rules = [];
        $labels = [];
        $errors = [];
        $id = $row['id'] ?? null;
        foreach ($fields as $f) {
            $n = $f['name'];
            $labels[$n] = $f['label'];
            $type = $f['type'] ?? 'text';
            if (in_array($type, ['checkbox', 'image', 'file', 'multicheck'], true)) {
                continue;
            }
            $rule = $f['rules'] ?? '';
            if (!empty($f['unique'])) {
                $rule = trim($rule . "|unique:{$r['table']},$n" . ($id ? ",$id" : ''), '|');
            }
            $rules[$n] = $rule;
        }
        $data = Validator::check($_POST, $rules, $labels);

        foreach ($fields as $f) {
            $n = $f['name'];
            $type = $f['type'] ?? 'text';
            if ($type === 'checkbox') {
                $data[$n] = isset($_POST[$n]) ? 1 : 0;
            } elseif ($type === 'multicheck') {
                $vals = array_map('intval', (array) ($_POST[$n] ?? []));
                foreach ($vals as $v) {
                    if (!Lookup::has($f['lookup'], $v)) {
                        $errors[$n] = "{$f['label']} has an invalid value.";
                    }
                }
                $data[$n] = $vals;
            } elseif ($type === 'slug') {
                $base = slugify($data[$n] ?: ($_POST[$f['source']] ?? ''));
                $data[$n] = $this->uniqueSlug($r['table'], $base, $id);
            } elseif ($type === 'select') {
                $v = $data[$n];
                if ($v !== null) {
                    $ok = isset($f['lookup']) ? Lookup::has($f['lookup'], $v) : array_key_exists($v, $f['options']);
                    // Keeps an existing value valid if its option was since deactivated.
                    $unchanged = $row !== null && isset($row[$n]) && (string) $row[$n] === (string) $v;
                    if (!$ok && !$unchanged) {
                        $errors[$n] = "{$f['label']} has an invalid value.";
                    }
                }
            } elseif ($type === 'image' || $type === 'file') {
                unset($data[$n]);
                if (Uploader::hasFile($n)) {
                    try {
                        $data[$n] = $type === 'image' ? Uploader::image($n, $slug) : Uploader::document($n);
                    } catch (ValidationException $e) {
                        $errors += $e->errors;
                    }
                    if ($row && !empty($row[$n]) && $type === 'image') {
                        Uploader::deletePublic($row[$n]);
                    }
                } elseif (isset($_POST["_remove_$n"]) && $row) {
                    if ($type === 'image') {
                        Uploader::deletePublic($row[$n] ?? null);
                    }
                    $data[$n] = null;
                }
            }
            if (array_key_exists($n, $data) && $data[$n] === null && array_key_exists('default', $f)) {
                $default = $f['default'];
                $data[$n] = in_array($default, ['today', 'this_month'], true) ? $data[$n] : $default;
            }
        }
        if ($errors) {
            throw new ValidationException($errors);
        }
        if (!empty($data['farm_id'])) {
            Auth::assertFarm((int) $data['farm_id']);
        }
        return $data;
    }

    private function columnsOnly(array $r, array $data): array
    {
        foreach ($r['fields'] as $f) {
            if (!empty($f['virtual'])) {
                unset($data[$f['name']]);
            }
        }
        return $data;
    }

    private function uniqueSlug(string $table, string $base, ?int $id): string
    {
        $slug = $base;
        $i = 2;
        while (DB::value("SELECT COUNT(*) FROM `$table` WHERE slug=?" . ($id ? ' AND id<>?' : ''), $id ? [$slug, $id] : [$slug])) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }
}
