# AI assistance

The maintained source is [`skills/yii2-roadrunner-worker/SKILL.md`](../skills/yii2-roadrunner-worker/SKILL.md). It
guides factory registration, the worker loop, response finalization, and request isolation. It does not install or start
the server.

## Install in an application

### Automatic Composer installation

Complete the
[one-time application setup](https://yii2-framework.github.io/docs/project/ai-agents/#composer-installation) to enable
`llm/skills` and set `auto-sync` to `true` in `skills.json`. Approve `yii2-extensions/road-runner` explicitly in
`dependencies.composer.trusted`. The installed package must include its skill bundle; its `extra.skills.source` declares
the directory.

```bash
composer install
composer skills:show
```

With `auto-sync` enabled, later dependency installs and updates synchronize the skill automatically; no per-skill
installer is required. If `auto-sync` is `false`, run `composer skills:update` after dependency installation or updates.

### Optional npx installation

Run from the application's root, selecting the clients you use:

```bash
npx skills@1.7.2 add /absolute/path/to/road-runner --list
npx skills@1.7.2 add /absolute/path/to/road-runner \
  --skill yii2-roadrunner-worker --agent claude-code codex opencode
```

The source can be a checkout or `./vendor/yii2-extensions/road-runner` when the installed package includes the skill.
For GitHub sources, list first to confirm it is available:

```bash
npx skills@1.7.2 add yii2-extensions/road-runner --list
npx skills@1.7.2 add yii2-extensions/road-runner \
  --skill yii2-roadrunner-worker --agent claude-code codex opencode
```

Keep project scope. The installer links by default; `--copy` creates a snapshot to refresh after upgrades. Composer
installation alone does not expose skills in `vendor/` to agents running at the application root.

| Client      | Project path                                     | Explicit activation                           |
| ----------- | ------------------------------------------------ | --------------------------------------------- |
| Claude Code | `.claude/skills/yii2-roadrunner-worker/SKILL.md` | `/yii2-roadrunner-worker`                     |
| Codex       | `.agents/skills/yii2-roadrunner-worker/SKILL.md` | `$yii2-roadrunner-worker` or the skill picker |
| OpenCode    | `.agents/skills/yii2-roadrunner-worker/SKILL.md` | Ask it to load `yii2-roadrunner-worker`       |

For manual installation, copy or link the complete skill directory, preserving `<name>/SKILL.md`. This checkout links
`.agents/skills/` and `.claude/skills/` to the canonical `skills/` source; do not edit independent copies.

## Verify

Check the installed directory and frontmatter, then open a fresh client session at the application root. Confirm
discovery in its skill list and activate it explicitly. Installer `--list`, client discovery, activation, and functional
application tests are separate evidence. Automatic selection depends on the client and model.

The skill routes to the relevant package guide or upstream Markdown at the installed revision. It needs no private
maintainer tools. Finish with [package and runtime checks](testing.md#worker-compatibility-checks).

## Example prompts

Start a fresh client session at the application root. The explicit example uses Codex's `$skill-name` notation; use
`/yii2-roadrunner-worker` in Claude Code or the installed client's native skill mechanism. Do not paste the skill body
and count that as native activation. These are tasks to execute and verify, not claims that an evaluation has passed.

### Explicit invocation

```text
$yii2-roadrunner-worker
Review a RoadRunner worker using the installed bridge and factory interfaces. Show how to test one finalization per
attempt and recycling before a pending dispatch is handled, without writing protocol-corrupting output to stdout.
Inspect the installed revision first. Do not install dependencies or change files without authorization.
```

### Natural selection

```text
An after-send callback throws in my RoadRunner worker. How can I check that the request is not emitted or finalized
twice and that shutdown failures remain observable?
```

### Verify the outcome

Use observable call ordering and failure assertions, then run a native worker test when available. Mocked transport
coverage is not native runtime proof. Record native loading evidence separately from configuration review, executable
checks and browser/runtime results. For a negative selection control, ask an unrelated question in a fresh session and
confirm this package skill is not loaded. Keep evaluation traces private; report unavailable checks rather than treating
discovery as execution.

[Back to documentation](../README.md#documentation)
