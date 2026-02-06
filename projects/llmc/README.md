# llmc

**llmc** is a deterministic, rule-based guardrail engine for evaluating and correcting LLM output.

It scores responses against explicit policy rules, reports violations, and supports self-correction loops **without exposing scoring internals to the model**.

This project is intentionally simple, auditable, and non-magical.

---

## Mental model

- Rules are **binary**: violated or not
- Each violation adds a **penalty** (golf scoring: lower is better)
- **PASS** means zero violations
- **FAIL** means one or more violations
- Multiple violations in a single response are **normal**

llmc does not guess, rank, or infer intent. It only evaluates explicit rule breaks.

---

## What llmc is

- A deterministic policy enforcement engine
- A guardrail for LLM responses
- A tool for audit, enforcement, and self-correction loops

## What llmc is not

- Not a classifier
- Not probabilistic
- Not self-modifying
- Not allowed to change its own rules
- Not a safety filter or content moderator

---

## Usage (CLI)

List evaluation questions for a rule set:

```bash
php llmcs.php questions --sets=debug,coding
```

Score a response (LLM-facing output):

```bash
echo "text" | php llmcs.php score --sets=debug,coding --answers=YYN...
```

Audit view (human-facing, includes numeric breakdown):

```bash
echo "text" | php llmcs.php score --sets=debug,coding --answers=YYN... --report=audit
```

> An empty response with `PASS` is valid behavior.

---

## Configuration

Policy is defined in `llmc.json`:

- `rules` are atomic violations
- `groups` compose rules for different contexts
- Rules are enforced independently

Configuration is **data**, not code.

---

## Design constraints

- Explicit > clever
- Auditable > opaque
- Deterministic > probabilistic
- Config ≠ code

If a rule fires, it must be explainable in plain English.
