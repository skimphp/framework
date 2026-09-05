<?php declare(strict_types=1);

namespace Skim\Db;

/**
 * Immutable value object returned by query_scope::paginate(). #AI:class
 *
 * Use when rendering paginated lists — provides items, total count, page
 * navigation booleans, and computed page count via property hooks.
 *
 * Example:
 *   $page = user::where(['status' => 'active'])->paginate(page: 2, per_page: 20);
 *   // $page->items, $page->total, $page->pages, $page->has_next, $page->has_prev
 *
 * Testing: Construct directly — new pagination(items: [], total: 0, per_page: 20, current: 1).
 *
 * #AI:class
 */
final class Pagination {
    public function __construct(
        public readonly array $items,
        public readonly int   $total,
        public readonly int   $perPage,
        public readonly int   $current,
    ) {}

    /**
     * Total number of pages, computed from total and per_page. #AI:pages
     */
    public int $pages {
        get => (int) ceil($this->total / max(1, $this->perPage));
    }

    /**
     * True when there is a next page beyond the current one. #AI:has_next
     */
    public bool $has_next {
        get => $this->current < $this->pages;
    }

    /**
     * True when the current page is greater than 1. #AI:has_prev
     */
    public bool $has_prev {
        get => $this->current > 1;
    }
}

#AI:class
#AI symbol: Skim\Db\Pagination
#AI source_path: src/db/pagination.php
#AI title: pagination
#AI description: Immutable result object for paginated queries with computed page navigation properties.
#AI role: pagination value object
#AI layer: db
#AI badges: [value-object; pagination; immutable]
#AI intro: `pagination` is an immutable value object returned by `query_scope::paginate()`. It holds the current page of hydrated model instances, total count, and provides computed navigation properties via PHP 8.4 property hooks.
#AI lifecycle: created by query_scope::paginate(), consumed by controllers and views
#AI fallback: none
#AI test_seam: construct directly with known values
#AI invariants: [all constructor properties are readonly; pages/has_next/has_prev are computed, not stored; per_page is clamped to min 1 for division safety]
#AI core_behaviors: [Property hooks compute pages, has_next, has_prev on each access]
#AI warnings: []
#AI notes: PHP 8.5 clone-with syntax can create modified copies if needed.
#AI scope_items: []
#AI owns: items array
#AI entry_points: [__construct]
#AI config_reads: []
#AI non_goals: [Does not execute queries — query_scope handles that; Does not render HTML pagination controls]
#AI side_effects: []
#AI flow: query_scope::paginate() -> count() + all() -> new pagination(items, total, per_page, current)
#AI lifecycle_steps: [query_scope::paginate(page, per_page); -> count() for total; -> limit/per_page + offset calculation; -> all() for items; -> new pagination(...)]
#AI section_order: [Constructor; Computed Properties]
#AI architectural_notes: Pure value object — no DB access, no side effects. Property hooks keep the API clean without storing redundant computed fields.

#AI:__construct
#AI group: Constructor
#AI frequency: internal
#AI signature: public function __construct(array $items, int $total, int $perPage, int $current)
#AI contract: Stores the page of results and pagination metadata. All properties are readonly.
#AI param_details: [{name: $items | type: array | required: true | desc: Hydrated model instances for the current page.}; {name: $total | type: int | required: true | desc: Total matching rows across all pages.}; {name: $perPage | type: int | required: true | desc: Number of items per page.}; {name: $current | type: int | required: true | desc: Current page number (1-indexed).}]

#AI:pages
#AI group: Computed Properties
#AI frequency: high
#AI signature: public int $pages { get }
#AI contract: Returns total page count, computed as ceil(total / per_page). Clamps per_page to min 1 to avoid division by zero.
#AI return_detail: {type: int | desc: Total number of pages.}

#AI:has_next
#AI group: Computed Properties
#AI frequency: high
#AI signature: public bool $hasNext { get }
#AI contract: Returns true when the current page is less than the total page count.
#AI return_detail: {type: bool | desc: True if a next page exists.}

#AI:has_prev
#AI group: Computed Properties
#AI frequency: high
#AI signature: public bool $hasPrev { get }
#AI contract: Returns true when the current page is greater than 1.
#AI return_detail: {type: bool | desc: True if a previous page exists.}
