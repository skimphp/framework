<?php declare(strict_types=1);

namespace skim\view;

// Template context object. `$this` inside every .php view is a template instance.
// Provides include(), layout(), start(), end(), slot() — the full layout system.
//
// Layout execution order: child template runs first (capturing slots), then
// layout file renders and calls $this->slot() to inject captured content.
// Why this order: layout needs slot content before it can render.
class template {
    private string  $views_path;
    private array   $data;
    private ?string $layout_name  = null;
    private array   $slots        = [];   // name → captured HTML
    private ?string $active_slot  = null;

    public function __construct(string $views_path, array $data = []) {
        $this->views_path = rtrim($views_path, '/');
        $this->data       = $data;
    }

    /**
     * @ai-contract renders $template file within current data context
     * @ai-contract $template is relative to views path, no extension needed
     */
    public function include(string $template, array $extra = []): string {
        return (new self($this->views_path, array_merge($this->data, $extra)))->render_file($template);
    }

    /**
     * @ai-contract declares the layout to wrap this template — must be called before any output
     * @ai-contract layout file uses $this->slot() to inject child content
     */
    public function layout(string $name): void {
        $this->layout_name = $name;
    }

    /**
     * @ai-contract starts capturing output into a named slot
     * @ai-contract must be paired with end()
     */
    public function start(string $name): void {
        $this->active_slot = $name;
        ob_start();
    }

    /**
     * @ai-contract ends slot capture started by start()
     * @ai-contract captured output stored in slot — flushed by layout via slot()
     */
    public function end(): void {
        if ($this->active_slot === null) {
            throw new \LogicException('end() called without matching start()');
        }
        $this->slots[$this->active_slot] = (string) ob_get_clean();
        $this->active_slot               = null;
    }

    /**
     * @ai-contract outputs the captured slot content inside a layout file
     * @ai-contract returns empty string if slot was never captured (missing start/end pair)
     */
    public function slot(string $name): string {
        return $this->slots[$name] ?? '';
    }

    // Called by view::render() to execute the template file
    public function render_file(string $template): string {
        $file = $this->views_path . '/' . ltrim($template, '/') . '.php';

        if (!is_file($file)) {
            throw new exceptions\view_exception("View template not found: {$template}");
        }

        // Extract data as local variables inside the template
        extract($this->data, \EXTR_SKIP);

        ob_start();
        include $file;
        $content = (string) ob_get_clean();

        // If a layout was declared, wrap content and render layout
        if ($this->layout_name !== null) {
            // Capture any non-slot output as 'content' slot if not already set
            if (!isset($this->slots['content'])) {
                $this->slots['content'] = $content;
            }
            $layout = new self($this->views_path, $this->data);
            $layout->slots = $this->slots;
            return $layout->render_file($this->layout_name);
        }

        return $content;
    }
}
