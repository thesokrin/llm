# llmc

**llmc** is a deterministic, rule-based guardrail engine for enforcing explicit policy rules on LLM output.

It evaluates responses against atomic violations, reports failures, and supports self-correction loops **without exposing scoring internals to the model**.

This project is intentionally simple, auditable, and non-magical.

---

## Why llmc exists

Many LLM guardrail approaches are opaque, probabilistic, or tightly coupled to the model.
That makes them harder to audit, harder to trust, and easier to game.

llmc takes a different approach: explicit rules, binary decisions, and deterministic outcomes.
If a response fails, it fails for a reason you can read and explain.

---

## Mental model

- Rules are **binary**: violated or not
- Each violation adds a **penalty** (golf scoring: lower is better)
- **PASS** means zero violations
- **FAIL** means one or more violations
- Multiple violations in a single response are **normal**

llmc does not guess, rank, or infer intent. It only evaluates explicit rule breaks.

---

## Example

Input response:

```text
The pipeline is definitely working and nothing is broken.
```

Possible violations:
- `certainty` (certainty/proof language without verification)
- `prove_it` (claims of verification without evidence/output)

LLM-facing result:

```text
FAIL
y_rules: certainty,prove_it
```

A self-correction loop should revise the response to eliminate **all** listed violations.

---

## What llmc is

- A deterministic policy enforcement engine
- A guardrail for LLM responses
- A tool for audit, enforcement, and self-correction loops

## What llmc is not

- Not a classifier
- Not probabilistic
- Not self-modifying
- Not allowed to change its own policy/guardrails
- Not a general safety filter or content moderator

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

## Design invariants

- Rules are evaluated independently
- Multiple violations per response are expected
- Passing requires **zero** violations
- Rules do not negotiate or prioritize
- The engine never modifies its own policy

---

## Design constraints

- Explicit > clever
- Auditable > opaque
- Deterministic > probabilistic
- Config ≠ code

If a rule fires, it must be explainable in plain English.
