<?php declare(strict_types=1);

namespace Skim\Db\Exceptions;

/**
 * Thrown by findOrFail() when no record matches the primary key. #AI:class
 *
 * Use in controllers to produce typed 404 responses. Always catch
 * not_found_exception specifically — never catch generic \Exception.
 *
 * Example:
 *   $user = User::findOrFail($id); // throws not_found_exception if missing
 *   // In error handler: catch → $res->status(404)->json(['error' => $e->getMessage()])
 *
 * Testing: Assert thrown with expect(fn() => User::findOrFail(999))->toThrow(NotFoundException::class).
 *
 * #AI:class
 */
class NotFoundException extends \RuntimeException {
    /**
     * Builds a "model with id X not found" message. #AI:__construct
     *
     * @param string     $model Fully-qualified model class name.
     * @param int|string $id    The primary key value that was searched.
     */
    public function __construct(string $model, int|string $id) {
        parent::__construct("{$model} with id '{$id}' not found.");
    }
}

#AI:class
#AI symbol: Skim\Db\Exceptions\NotFoundException
#AI source_path: src/Db/Exceptions/NotFoundException.php
#AI title: NotFoundException
#AI description: RuntimeException thrown by Model::findOrFail() when a record is missing.
#AI role: 404 model exception
#AI layer: db
#AI badges: [exception; orm; 404]
#AI intro: `NotFoundException` is thrown by `Model::findOrFail()` when no record matches the given primary key. Controllers catch it to return structured 404 responses.
#AI lifecycle: thrown by findOrFail(), caught by controller or global error handler
#AI fallback: none
#AI test_seam: expect(fn() => Model::findOrFail(999))->toThrow(NotFoundException::class)
#AI invariants: [message includes model class name and searched id; extends RuntimeException]
#AI core_behaviors: [Provides a human-readable error message identifying the model and missing id]
#AI warnings: []
#AI notes: Prefer `find()` returning null when absence is expected. Use `findOrFail()` only when missing is exceptional.
#AI scope_items: []
#AI owns: nothing
#AI entry_points: [__construct]
#AI config_reads: []
#AI non_goals: [Does not handle HTTP status codes — that is the controller's job]
#AI side_effects: []
#AI flow: Model::findOrFail() -> find() returns null -> throw new NotFoundException(class, id)
#AI lifecycle_steps: [Model::findOrFail($id); -> find($id) returns null; -> throw new NotFoundException(static::class, $id)]
#AI section_order: [Constructor]
#AI architectural_notes: Keeps the ORM layer HTTP-agnostic — the exception carries model context, the controller decides the HTTP response.

#AI:__construct
#AI group: Constructor
#AI frequency: internal
#AI signature: public function __construct(string $model, int|string $id)
#AI contract: Builds a "{model} with id '{id}' not found." message.
#AI param_details: [{name: $model | type: string | required: true | desc: Fully-qualified model class name.}; {name: $id | type: int|string | required: true | desc: The primary key value that was searched.}]
