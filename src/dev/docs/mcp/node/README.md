# @skim/mcp

MCP server for the [SKIM PHP micro-framework](https://github.com/skim-framework/skim).

Exposes your `llm.json` (generated from `@ai.*` PHPDoc annotations) as MCP tools usable from Claude Code, Cursor, and any MCP-compatible client.

---

## Prerequisites

- PHP 8.5+ in `$PATH`
- SKIM project with `llm.json` generated (`php skim docs:extract`)

---

## Install

```bash
npm install -g @skim/mcp
```

---

## Usage

### Claude Code

Add to `.claude/mcp_settings.json`:

```json
{
  "mcpServers": {
    "skim": {
      "command": "skim-mcp",
      "env": {
        "SKIM_ROOT": "/absolute/path/to/your/skim/project"
      }
    }
  }
}
```

### Cursor

Add to `.cursor/mcp.json`:

```json
{
  "mcpServers": {
    "skim": {
      "command": "skim-mcp",
      "env": {
        "SKIM_ROOT": "/absolute/path/to/your/skim/project"
      }
    }
  }
}
```

### Direct (stdio)

```bash
SKIM_ROOT=/path/to/project skim-mcp
```

---

## Available tools

| Tool | Description |
|------|-------------|
| `skim_class(name)` | Summary, lifecycle, owner, file path for a class |
| `skim_method(class, method)` | Signature, contracts, invariants, non_goals, side_effects, perf, throws |
| `skim_search(query)` | Fuzzy search across class names, method names, and contract text |
| `skim_lifecycle()` | Boot order and request lifecycle as a structured list |
| `skim_non_goals()` | All `@ai.non_goal` entries grouped by class |

---

## How it works

This package is a thin Node.js pipe. All intelligence lives in PHP:

```
Claude / Cursor  →  skim-mcp (Node)  →  php skim mcp:serve  →  llm.json
```

No PHP logic is duplicated in JavaScript.

---

## Generate / update llm.json

```bash
cd /path/to/project
php skim docs:extract   # scan @ai.* annotations → llm.json
php skim docs:llm       # llm.json → llm.md (optional, for paste-to-LLM workflow)
```

Or run everything at once:

```bash
php skim docs
```
