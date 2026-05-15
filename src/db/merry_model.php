<?php declare(strict_types=1);

namespace skim\db;

// Merry ORM — extends model with eager/lazy relation loading.
// Relations declared as static arrays — not annotations.
// Why static arrays: IDE-navigable, no reflection magic, explicit contracts.
//
// Eager loading with() uses IN queries — avoids N+1 automatically.
// Example: post::with('author', 'tags') → 3 queries max regardless of post count.
//
// PHP 8.5 pipe operator recommended for relation chains:
//   post::where(['status' => 'published'])->all()
//     |> fn($posts) => merry_model::with_loaded('tags', $posts)
abstract class merry_model extends model {
    // Subclass declares:
    // protected static array $has_many    = ['posts' => ['class' => post::class, 'fk' => 'user_id']];
    // protected static array $has_one     = ['profile' => ['class' => profile::class, 'fk' => 'user_id']];
    // protected static array $belongs_to  = ['author' => ['class' => user::class, 'fk' => 'user_id', 'pk' => 'id']];
    // protected static array $many_to_many = ['tags' => ['class' => tag::class, 'pivot' => 'post_tag', 'fk' => 'post_id', 'rfk' => 'tag_id']];

    protected static array $has_many     = [];
    protected static array $has_one      = [];
    protected static array $belongs_to   = [];
    protected static array $many_to_many = [];

    // Loaded relations cache — avoids re-fetching on repeat access
    private array $relations = [];

    /**
     * @ai-contract eager-loads named relations onto a collection of models — prevents N+1
     * @ai-contract $relations: 'posts', 'posts.comments' (dot = nested eager load)
     * @ai-contract returns the same $models array with relations populated
     */
    public static function with(array $models, string ...$relations): array {
        foreach ($relations as $relation) {
            $parts   = explode('.', $relation, 2);
            $name    = $parts[0];
            $nested  = $parts[1] ?? null;

            $models = static::eager_load($models, $name);

            if ($nested !== null) {
                // Gather all loaded related objects and eager-load the nested relation on them
                $related_all = [];
                foreach ($models as $model) {
                    $related = $model->relations[$name] ?? [];
                    foreach ((is_array($related) ? $related : [$related]) as $r) {
                        if ($r instanceof merry_model) {
                            $related_all[] = $r;
                        }
                    }
                }
                if ($related_all !== []) {
                    static::with($related_all, $nested);
                }
            }
        }
        return $models;
    }

    /**
     * @ai-contract accesses a relation — lazy-loads on first access, cached thereafter
     * @ai-contract for eager-loaded relations: returns cached value immediately
     */
    public function load(string $name): mixed {
        if (array_key_exists($name, $this->relations)) {
            return $this->relations[$name];
        }
        return $this->relations[$name] = $this->lazy_load($name);
    }

    // --- pivot operations (many-to-many) ---

    /**
     * @ai-contract attaches related IDs via pivot table — INSERT IGNORE duplicates
     */
    public function attach(string $relation, array $ids): void {
        $def = static::$many_to_many[$relation]
            ?? throw new \InvalidArgumentException("Relation '{$relation}' is not a many_to_many.");

        foreach ($ids as $id) {
            db::query(
                'INSERT IGNORE INTO ' . $def['pivot'] . ' %values%',
                ['values' => [$def['fk'] => $this->id, $def['rfk'] => $id]],
                connection: static::$connection,
            );
        }
    }

    /**
     * @ai-contract detaches related IDs from pivot table — DELETE WHERE
     */
    public function detach(string $relation, array $ids = []): void {
        $def = static::$many_to_many[$relation]
            ?? throw new \InvalidArgumentException("Relation '{$relation}' is not a many_to_many.");

        if ($ids === []) {
            db::query(
                'DELETE FROM ' . $def['pivot'] . ' WHERE ' . $def['fk'] . ' = :fk',
                [':fk' => $this->id],
                connection: static::$connection,
            );
        } else {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            db::query(
                'DELETE FROM ' . $def['pivot'] . ' WHERE ' . $def['fk'] . ' = ? AND ' . $def['rfk'] . ' IN (' . $placeholders . ')',
                array_merge([$this->id], $ids),
                connection: static::$connection,
            );
        }
    }

    /**
     * @ai-contract syncs pivot table to exactly $ids — detaches removed, attaches new
     */
    public function sync(string $relation, array $ids): void {
        $this->detach($relation);
        if ($ids !== []) {
            $this->attach($relation, $ids);
        }
    }

    // --- internals ---

    private static function eager_load(array $models, string $name): array {
        if ($models === []) {
            return $models;
        }

        // Determine relation type
        if (isset(static::$has_many[$name])) {
            return static::eager_has_many($models, $name, static::$has_many[$name]);
        }
        if (isset(static::$has_one[$name])) {
            return static::eager_has_one($models, $name, static::$has_one[$name]);
        }
        if (isset(static::$belongs_to[$name])) {
            return static::eager_belongs_to($models, $name, static::$belongs_to[$name]);
        }
        if (isset(static::$many_to_many[$name])) {
            return static::eager_many_to_many($models, $name, static::$many_to_many[$name]);
        }

        throw new \InvalidArgumentException("Relation '{$name}' not defined on " . static::class);
    }

    private static function eager_has_many(array $models, string $name, array $def): array {
        /** @var model $class */
        $class   = $def['class'];
        $fk      = $def['fk'];
        $pks     = array_unique(array_column(array_map(fn($m) => $m->to_array(), $models), static::$primary));
        $in      = implode(',', array_fill(0, count($pks), '?'));
        $related = $class::raw("SELECT * FROM {$class::get_table()} WHERE {$fk} IN ({$in})", $pks);

        $map = [];
        foreach ($related as $r) {
            $map[$r->$fk][] = $r;
        }
        foreach ($models as $m) {
            $m->relations[$name] = $map[$m->{static::$primary}] ?? [];
        }
        return $models;
    }

    private static function eager_has_one(array $models, string $name, array $def): array {
        /** @var model $class */
        $class   = $def['class'];
        $fk      = $def['fk'];
        $pks     = array_unique(array_column(array_map(fn($m) => $m->to_array(), $models), static::$primary));
        $in      = implode(',', array_fill(0, count($pks), '?'));
        $related = $class::raw("SELECT * FROM {$class::get_table()} WHERE {$fk} IN ({$in})", $pks);

        $map = [];
        foreach ($related as $r) {
            $map[$r->$fk] = $r;
        }
        foreach ($models as $m) {
            $m->relations[$name] = $map[$m->{static::$primary}] ?? null;
        }
        return $models;
    }

    private static function eager_belongs_to(array $models, string $name, array $def): array {
        /** @var model $class */
        $class   = $def['class'];
        $fk      = $def['fk'];
        $pk      = $def['pk'] ?? 'id';
        $fk_vals = array_unique(array_filter(array_column(array_map(fn($m) => $m->to_array(), $models), $fk)));
        $in      = implode(',', array_fill(0, count($fk_vals), '?'));
        $related = $class::raw("SELECT * FROM {$class::get_table()} WHERE {$pk} IN ({$in})", $fk_vals);

        $map = [];
        foreach ($related as $r) {
            $map[$r->$pk] = $r;
        }
        foreach ($models as $m) {
            $m->relations[$name] = $map[$m->$fk] ?? null;
        }
        return $models;
    }

    private static function eager_many_to_many(array $models, string $name, array $def): array {
        $class   = $def['class'];
        $pivot   = $def['pivot'];
        $fk      = $def['fk'];
        $rfk     = $def['rfk'];
        $pks     = array_unique(array_column(array_map(fn($m) => $m->to_array(), $models), static::$primary));
        $in      = implode(',', array_fill(0, count($pks), '?'));
        $rows    = db::all(
            "SELECT p.{$fk}, r.* FROM {$pivot} p JOIN {$class::get_table()} r ON p.{$rfk} = r.id WHERE p.{$fk} IN ({$in})",
            $pks, connection: static::$connection,
        );

        $map = [];
        foreach ($rows as $row) {
            $owner_id  = $row[$fk];
            unset($row[$fk]);
            $map[$owner_id][] = $class::hydrate_one($row);
        }
        foreach ($models as $m) {
            $m->relations[$name] = $map[$m->{static::$primary}] ?? [];
        }
        return $models;
    }

    private function lazy_load(string $name): mixed {
        if (isset(static::$has_many[$name])) {
            $def = static::$has_many[$name];
            return $def['class']::where([$def['fk'] => $this->id])->all();
        }
        if (isset(static::$has_one[$name])) {
            $def = static::$has_one[$name];
            return $def['class']::find_by($def['fk'], $this->id);
        }
        if (isset(static::$belongs_to[$name])) {
            $def = static::$belongs_to[$name];
            return $def['class']::find($this->{$def['fk']});
        }
        if (isset(static::$many_to_many[$name])) {
            return $this->load_pivot($name, static::$many_to_many[$name]);
        }
        throw new \InvalidArgumentException("Relation '{$name}' not defined on " . static::class);
    }

    private function load_pivot(string $name, array $def): array {
        $class = $def['class'];
        $pivot = $def['pivot'];
        $fk    = $def['fk'];
        $rfk   = $def['rfk'];
        $rows  = db::all(
            "SELECT r.* FROM {$pivot} p JOIN {$class::get_table()} r ON p.{$rfk} = r.id WHERE p.{$fk} = :fk",
            [':fk' => $this->id], connection: static::$connection,
        );
        return $class::hydrate_many($rows);
    }
}
