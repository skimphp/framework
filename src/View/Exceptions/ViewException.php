<?php declare(strict_types=1);

namespace Skim\View\Exceptions;

/**
 * Thrown when a template file is not found or a fragment name is missing. #AI:class
 *
 * Use in catch blocks to distinguish view errors from other runtime exceptions.
 *
 * Example:
 *   try {
 *       View::render('nonexistent');
 *   } catch (ViewException $e) {
 *       Log::error($e->getMessage());
 *   }
 *
 * Testing: Expect this exception when testing missing templates or fragments.
 *
 * #AI:class
 */
class ViewException extends \RuntimeException {}

#AI:class
#AI symbol: Skim\View\Exceptions\ViewException
#AI source_path: src/View/Exceptions/ViewException.php
#AI title: ViewException
#AI description: Exception thrown when a template file is not found or a named fragment is missing.
#AI role: view error type
#AI layer: view
#AI badges: [exception; view; error]
#AI intro: `ViewException` is thrown by the view system when a template file cannot be resolved or a requested fragment name does not exist in the rendered output.
#AI lifecycle: thrown during View::render() or Template::renderFile()
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
#AI flow: View::render() -> template not found or fragment missing -> throw ViewException
#AI lifecycle_steps: [View::render() or Template::renderFile(); -> file/fragment not found; -> throw new ViewException(...)]
#AI section_order: [Architecture]
#AI architectural_notes: Simple RuntimeException subclass — exists solely for type-specific catching.
