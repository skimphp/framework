<?php declare(strict_types=1);

namespace Skim\View\Exceptions;

/**
 * Thrown when a template file is not found or a fragment name is missing. #AI:class
 *
 * Use in catch blocks to distinguish view errors from other runtime exceptions.
 *
 * Example:
 *   try {
 *       view::render('nonexistent');
 *   } catch (view_exception $e) {
 *       log::error($e->getMessage());
 *   }
 *
 * Testing: Expect this exception when testing missing templates or fragments.
 *
 * #AI:class
 */
class ViewException extends \RuntimeException {}

#AI:class
#AI symbol: Skim\View\Exceptions\ViewException
#AI source_path: src/view/exceptions/view_exception.php
#AI title: view_exception
#AI description: Exception thrown when a template file is not found or a named fragment is missing.
#AI role: view error type
#AI layer: view
#AI badges: [exception; view; error]
#AI intro: `view_exception` is thrown by the view system when a template file cannot be resolved or a requested fragment name does not exist in the rendered output.
#AI lifecycle: thrown during view::render() or template::render_file()
#AI fallback: n/a — exception type
#AI test_seam: expect this exception in Pest tests for missing templates
#AI invariants: [Extends RuntimeException; Thrown only for template-not-found and fragment-not-found errors]
#AI core_behaviors: [Provides a specific exception type for view-layer errors]
#AI notes: Distinguishable from generic RuntimeException for targeted error handling.
#AI owns: nothing
#AI entry_points: []
#AI config_reads: []
#AI non_goals: [Does not handle rendering logic; Does not provide recovery mechanisms]
#AI side_effects: []
#AI flow: view::render() -> template not found or fragment missing -> throw view_exception
#AI lifecycle_steps: [view::render() or template::render_file(); -> file/fragment not found; -> throw new view_exception(...)]
#AI section_order: [Architecture]
#AI architectural_notes: Simple RuntimeException subclass — exists solely for type-specific catching.
