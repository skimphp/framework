<?php declare(strict_types=1);

namespace Skim\Db;

/**
 * ORM layer extending model with eager/lazy relation loading. #AI:class
 *
 * Use when models need has_many, has_one, belongs_to, or many_to_many relations.
 * Relations are declared as static arrays (not annotations) for IDE navigation
 * and explicit contracts. Eager loading via with() uses IN queries to prevent N+1.
 *
 * Example:
 *   class Post extends MerryModel {
 *       protected static string $table = 'posts';
 *       protected static array $belongs_to = ['author' => ['class' => User::class, 'fk' => 'user_id']];
 *       protected static array $many_to_many = ['tags' => ['class' => tag::class, 'pivot' => 'post_tag', 'fk' => 'post_id', 'rfk' => 'tag_id']];
 *   }
 *   $posts = Post::with(Post::where(['status' => 'published'])->all(), 'author', 'tags');
 *
 * Testing: Use test_db() SQLite :memory: with real relation tables.
 *
 * #AI:class
 */
abstract class MerryModel extends \Skim\Db\Model {
    protected static array $hasMany     = [];
    protected static array $hasOne      = [];
    protected static array $belongsTo   = [];
    protected static array $manyToMany = [];

    private array $relations = [];

    /**
     * Eager-loads named relations onto a collection of models — prevents N+1. #AI:with
     *
     * Supports dot-notation for nested eager loading (e.g. 'posts.comments').
     * Returns the same $models array with relations populated.
     *
     * Example:
     *   $users = MerryModel::with(User::all(), 'posts', 'posts.comments');
     *   // 3 queries max regardless of user/post count
     *
     * @param array  $models    Collection of model instances to load relations onto.
     * @param string ...$relations Relation names, supports dot-notation for nesting.
     */
    public static function with(array $models, string ...$relations): array {
        foreach ($relations as $relation) {
            $parts   = explode('.', $relation, 2);
            $name    = $parts[0];
            $nested  = $parts[1] ?? null;

            $models = static::eagerLoad($models, $name);

            if ($nested !== null) {
                $relatedAll = [];
                foreach ($models as $model) {
                    $related = $model->relations[$name] ?? [];
                    foreach ((is_array($related) ? $related : [$related]) as $r) {
                        if ($r instanceof \Skim\Db\MerryModel) {
                            $relatedAll[] = $r;
                        }
                    }
                }
                if ($relatedAll !== []) {
                    $relatedAll[0]::with($relatedAll, $nested);
                }
            }
        }
        return $models;
    }

    /**
     * Accesses a relation — lazy-loads on first access, cached thereafter. #AI:load
     *
     * For eager-loaded relations, returns the cached value immediately without
     * an additional query.
     *
     * @param string $name Relation name as declared in the static arrays.
     */
    public function load(string $name): mixed {
        if (array_key_exists($name, $this->relations)) {
            return $this->relations[$name];
        }
        return $this->relations[$name] = $this->lazyLoad($name);
    }

    // --- pivot operations (many-to-many) ---

    /**
     * Attaches related IDs via pivot table — INSERT IGNORE skips duplicates. #AI:attach
     *
     * @param string $relation many_to_many relation name.
     * @param array  $ids      Related model IDs to attach.
     * @throws \InvalidArgumentException If relation is not many_to_many.
     */
    public function attach(string $relation, array $ids): void {
        $def = static::$manyToMany[$relation]
            ?? throw new \InvalidArgumentException("Relation '{$relation}' is not a many_to_many.");

        $driver = \Skim\Db\Db::pdo(static::$connection)->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $verb = match ($driver) {
            'mysql'  => 'INSERT IGNORE INTO ',
            'pgsql'  => 'INSERT INTO ',
            default  => 'INSERT OR IGNORE INTO ',
        };
        foreach ($ids as $id) {
            try {
                \Skim\Db\Db::query(
                    $verb . $def['pivot'] . ' %values%',
                    ['values' => [$def['fk'] => $this->id, $def['rfk'] => $id]],
                    connection: static::$connection,
                );
            } catch (\Skim\Db\Exceptions\DbException) {
                // duplicate pivot row — attach is idempotent
            }
        }
    }

    /**
     * Detaches related IDs from pivot table. #AI:detach
     *
     * When $ids is empty, detaches ALL related records for this model.
     *
     * WARNING: Empty $ids removes all pivot rows for this model's foreign key.
     *
     * @param string $relation many_to_many relation name.
     * @param array  $ids      Specific IDs to detach. Empty = detach all.
     * @throws \InvalidArgumentException If relation is not many_to_many.
     */
    public function detach(string $relation, array $ids = []): void {
        $def = static::$manyToMany[$relation]
            ?? throw new \InvalidArgumentException("Relation '{$relation}' is not a many_to_many.");

        if ($ids === []) {
            \Skim\Db\Db::query(
                'DELETE FROM ' . $def['pivot'] . ' WHERE ' . $def['fk'] . ' = :fk',
                [':fk' => $this->id],
                connection: static::$connection,
            );
        } else {
            [$in, $inParams] = self::namedIn($ids);
            \Skim\Db\Db::query(
                'DELETE FROM ' . $def['pivot'] . ' WHERE ' . $def['fk'] . ' = :_fk AND ' . $def['rfk'] . ' IN (' . $in . ')',
                array_merge([':_fk' => $this->id], $inParams),
                connection: static::$connection,
            );
        }
    }

    /**
     * Syncs pivot table to exactly $ids — detaches removed, attaches new. #AI:sync
     *
     * WARNING: Detaches ALL existing pivot rows before re-attaching the given IDs.
     *
     * @param string $relation many_to_many relation name.
     * @param array  $ids      Exact set of related IDs to maintain.
     */
    public function sync(string $relation, array $ids): void {
        $this->detach($relation);
        if ($ids !== []) {
            $this->attach($relation, $ids);
        }
    }

    // --- internals ---

    private static function eagerLoad(array $models, string $name): array {
        if ($models === []) {
            return $models;
        }

        if (isset(static::$hasMany[$name])) {
            return static::eagerHasMany($models, $name, static::$hasMany[$name]);
        }
        if (isset(static::$hasOne[$name])) {
            return static::eagerHasOne($models, $name, static::$hasOne[$name]);
        }
        if (isset(static::$belongsTo[$name])) {
            return static::eagerBelongsTo($models, $name, static::$belongsTo[$name]);
        }
        if (isset(static::$manyToMany[$name])) {
            return static::eagerManyToMany($models, $name, static::$manyToMany[$name]);
        }

        throw new \InvalidArgumentException("Relation '{$name}' not defined on " . static::class);
    }

    private static function eagerHasMany(array $models, string $name, array $def): array {
        /** @var \Skim\Db\Model $class */
        $class   = $def['class'];
        $fk      = $def['fk'];
        $pks     = array_unique(array_column(array_map(fn($m) => $m->toArray(), $models), static::$primary));
        [$in, $inParams] = self::namedIn($pks);
        $related = $class::raw("SELECT * FROM {$class::getTable()} WHERE {$fk} IN ({$in})", $inParams);

        $map = [];
        foreach ($related as $r) {
            $map[$r->$fk][] = $r;
        }
        foreach ($models as $m) {
            $m->relations[$name] = $map[$m->{static::$primary}] ?? [];
        }
        return $models;
    }

    private static function eagerHasOne(array $models, string $name, array $def): array {
        /** @var \Skim\Db\Model $class */
        $class   = $def['class'];
        $fk      = $def['fk'];
        $pks     = array_unique(array_column(array_map(fn($m) => $m->toArray(), $models), static::$primary));
        [$in, $inParams] = self::namedIn($pks);
        $related = $class::raw("SELECT * FROM {$class::getTable()} WHERE {$fk} IN ({$in})", $inParams);

        $map = [];
        foreach ($related as $r) {
            $map[$r->$fk] = $r;
        }
        foreach ($models as $m) {
            $m->relations[$name] = $map[$m->{static::$primary}] ?? null;
        }
        return $models;
    }

    private static function eagerBelongsTo(array $models, string $name, array $def): array {
        /** @var \Skim\Db\Model $class */
        $class   = $def['class'];
        $fk      = $def['fk'];
        $pk      = $def['pk'] ?? 'id';
        $fkVals = array_unique(array_filter(array_column(array_map(fn($m) => $m->toArray(), $models), $fk)));
        [$in, $inParams] = self::namedIn($fkVals);
        $related = $class::raw("SELECT * FROM {$class::getTable()} WHERE {$pk} IN ({$in})", $inParams);

        $map = [];
        foreach ($related as $r) {
            $map[$r->$pk] = $r;
        }
        foreach ($models as $m) {
            $m->relations[$name] = $map[$m->$fk] ?? null;
        }
        return $models;
    }

    private static function eagerManyToMany(array $models, string $name, array $def): array {
        $class   = $def['class'];
        $pivot   = $def['pivot'];
        $fk      = $def['fk'];
        $rfk     = $def['rfk'];
        $pks     = array_unique(array_column(array_map(fn($m) => $m->toArray(), $models), static::$primary));
        [$in, $inParams] = self::namedIn($pks);
        $rows    = \Skim\Db\Db::all(
            "SELECT p.{$fk}, r.* FROM {$pivot} p JOIN {$class::getTable()} r ON p.{$rfk} = r.id WHERE p.{$fk} IN ({$in})",
            $inParams, connection: static::$connection,
        );

        $map = [];
        foreach ($rows as $row) {
            $ownerId  = $row[$fk];
            unset($row[$fk]);
            $map[$ownerId][] = $class::hydrateOne($row);
        }
        foreach ($models as $m) {
            $m->relations[$name] = $map[$m->{static::$primary}] ?? [];
        }
        return $models;
    }

    private function lazyLoad(string $name): mixed {
        if (isset(static::$hasMany[$name])) {
            $def = static::$hasMany[$name];
            return $def['class']::where([$def['fk'] => $this->id])->all();
        }
        if (isset(static::$hasOne[$name])) {
            $def = static::$hasOne[$name];
            return $def['class']::findBy($def['fk'], $this->id);
        }
        if (isset(static::$belongsTo[$name])) {
            $def = static::$belongsTo[$name];
            return $def['class']::find($this->{$def['fk']});
        }
        if (isset(static::$manyToMany[$name])) {
            return $this->loadPivot($name, static::$manyToMany[$name]);
        }
        throw new \InvalidArgumentException("Relation '{$name}' not defined on " . static::class);
    }

    private static function namedIn(array $values): array {
        $placeholders = [];
        $params       = [];
        foreach (array_values($values) as $i => $v) {
            $placeholders[]       = ':_in' . $i;
            $params[':_in' . $i] = $v;
        }
        return [implode(',', $placeholders), $params];
    }

    private function loadPivot(string $name, array $def): array {
        $class = $def['class'];
        $pivot = $def['pivot'];
        $fk    = $def['fk'];
        $rfk   = $def['rfk'];
        $rows  = \Skim\Db\Db::all(
            "SELECT r.* FROM {$pivot} p JOIN {$class::getTable()} r ON p.{$rfk} = r.id WHERE p.{$fk} = :fk",
            [':fk' => $this->id], connection: static::$connection,
        );
        return $class::hydrateMany($rows);
    }
}

#AI:class
#AI symbol: Skim\Db\MerryModel
#AI source_path: src/Db/MerryModel.php
#AI title: MerryModel
#AI description: ORM layer extending model with eager/lazy relation loading and many-to-many pivot operations.
#AI role: relational ORM
#AI layer: db
#AI badges: [orm; relations; eager-loading; pivot; n+1-safe]
#AI intro: `MerryModel` extends `model` with relation declarations (hasMany, hasOne, belongsTo, manyToMany) and eager/lazy loading. Relations are declared as static arrays for IDE navigation and explicit contracts. Eager loading via `with()` uses IN queries to prevent N+1 automatically.
#AI lifecycle: extends Model lifecycle — relations cached per instance after first load
#AI fallback: none
#AI test_seam: use test_db() SQLite :memory: with real relation tables
#AI invariants: [relations declared as static arrays, not annotations; eager loading uses IN queries — max N+1 queries where N = relation depth; lazy loading fires on first load() call and caches; pivot operations use INSERT IGNORE for idempotent attach]
#AI core_behaviors: [with() eager-loads using IN queries to prevent N+1; load() lazy-loads on first access and caches; attach/detach/sync manage manyToMany pivot tables; dot-notation supports nested eager loading]
#AI warnings: [detach() with empty $ids removes ALL pivot rows for this model; sync() detaches everything before re-attaching]
#AI notes: PHP 8.5 pipe operator recommended for relation chains.
#AI scope_items: []
#AI owns: relations cache per instance
#AI entry_points: [with; load; attach; detach; sync]
#AI config_reads: []
#AI non_goals: [Does not support polymorphic relations; Does not support through-relations; Does not auto-delete related records on parent delete]
#AI side_effects: [attach/detach/sync modify pivot tables; load() and with() execute SELECT queries]
#AI flow: Model::with(collection, 'relation') -> eagerLoad() -> IN query -> map results to models
#AI lifecycle_steps: [Model::with(models, ...relations); -> for each relation: eagerLoad(); -> determine relation type; -> IN query with all PKs; -> map results by FK; -> assign to model.relations; -> nested: recurse on loaded related models]
#AI section_order: [Eager Loading; Lazy Access; Pivot Operations]
#AI architectural_notes: Static array declarations keep relations IDE-navigable and avoid reflection magic. Eager loading uses a single IN query per relation regardless of collection size.

#AI:with
#AI group: Eager Loading
#AI frequency: high
#AI signature: public static function with(array $models, string ...$relations): array
#AI contract: Eager-loads named relations onto a collection of models using IN queries. Supports dot-notation for nested eager loading. Returns the same array with relations populated.
#AI param_details: [{name: $models | type: array | required: true | desc: Collection of model instances.}; {name: $relations | type: string | required: true | desc: Relation names. Use dot-notation for nesting (e.g. 'posts.comments').}]
#AI return_detail: {type: array | desc: Same models array with relations loaded.}
#AI side_effects: Executes one SELECT query per relation level.

#AI:load
#AI group: Lazy Access
#AI frequency: high
#AI signature: public function load(string $name): mixed
#AI contract: Returns a relation's value — lazy-loads on first access, returns cached value on subsequent calls. For eager-loaded relations, returns immediately.
#AI param_details: [{name: $name | type: string | required: true | desc: Relation name as declared in static arrays.}]
#AI return_detail: {type: mixed | desc: Model, array of models, or null depending on relation type.}
#AI side_effects: May execute a SELECT query on first access.

#AI:attach
#AI group: Pivot Operations
#AI frequency: medium
#AI signature: public function attach(string $relation, array $ids): void
#AI contract: Inserts pivot rows for the given IDs. INSERT IGNORE skips duplicates silently.
#AI param_details: [{name: $relation | type: string | required: true | desc: manyToMany relation name.}; {name: $ids | type: array | required: true | desc: Related model IDs to attach.}]
#AI throws_details: [{type: \InvalidArgumentException | desc: If relation is not declared as manyToMany.}]
#AI side_effects: Inserts rows into the pivot table.

#AI:detach
#AI group: Pivot Operations
#AI frequency: medium
#AI signature: public function detach(string $relation, array $ids = []): void
#AI contract: Deletes pivot rows for the given IDs. Empty $ids removes ALL pivot rows for this model.
#AI param_details: [{name: $relation | type: string | required: true | desc: manyToMany relation name.}; {name: $ids | type: array | required: false | desc: Specific IDs to detach. Empty = detach all.}]
#AI throws_details: [{type: \InvalidArgumentException | desc: If relation is not declared as manyToMany.}]
#AI warnings: [Empty $ids removes ALL pivot rows for this model's foreign key]
#AI side_effects: Deletes rows from the pivot table.

#AI:sync
#AI group: Pivot Operations
#AI frequency: medium
#AI signature: public function sync(string $relation, array $ids): void
#AI contract: Detaches all existing pivot rows, then attaches the given IDs. Result: pivot matches exactly $ids.
#AI param_details: [{name: $relation | type: string | required: true | desc: manyToMany relation name.}; {name: $ids | type: array | required: true | desc: Exact set of related IDs to maintain.}]
#AI warnings: [Detaches ALL existing pivot rows before re-attaching]
#AI side_effects: Deletes and inserts rows in the pivot table.
