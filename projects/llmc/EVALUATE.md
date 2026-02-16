# llmc: Drop-in Self-Evaluation Prompt

Use this when you want an LLM to **evaluate its immediately previous response** using llmc.

Policy source (canonical):
- https://raw.githubusercontent.com/thesokrin/llm/refs/heads/main/projects/llmc/llmc.json

## Drop-in prompt

Copy/paste this into the chat as the next message:

```
Evaluate your previous response using llmc.

1) Load policy JSON from:
https://raw.githubusercontent.com/thesokrin/llm/refs/heads/main/projects/llmc/llmc.json

2) Select all groups that apply to the type of response you just gave (you may select multiple groups).

3) For each selected group, evaluate the response against every rule ID listed in that group.
Use each rule's "prompt" exactly as written. A rule is violated if the answer to its prompt is YES.

4) PASS = zero violations. FAIL = one or more violations.
Compute total_penalty = sum of "severity" for all violated rules.

Output:
- Selected groups
- PASS/FAIL
- Violations (rule_id + 1 short sentence why)
- total_penalty
- 1-3 concrete fixes to make the response PASS

Do not invent rules or groups. Do not modify the policy.
```
