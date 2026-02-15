# Workflow: multi-LLM safe contributions

## Branching
- Create a branch per task: `llm/<short-task-name>`
- Do not push to `main`.

## Commit discipline
- One logical change per commit.
- Commit message must be specific (no "misc", "cleanup", "wip").
- Do not mix changes across multiple projects in the same commit.

Recommended trailer:
Generated-by: <model>
Reviewed-by: <human or blank>

## Secrets policy
- Do not commit any credentials, tokens, API keys, private URLs, or customer data.
- `.gitignore` helps, but it is not a guarantee: secrets can appear in any file type.
- If you suspect a secret was added, stop and revert before merge.

## Import policy
When importing external directories:
- Import into `projects/<name>/`
- Add/maintain `projects/<name>/README.md` describing purpose/runtime/entry point
