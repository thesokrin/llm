# llmc Response Evaluation Link

This file contains a concise prompt that can be linked in chat when a response should be evaluated under llmc.

Policy source (canonical):
https://raw.githubusercontent.com/thesokrin/llm/refs/heads/main/projects/llmc/llmc.json

---

## Drop-in Evaluation Prompt

Evaluate your **immediately previous response** using llmc.

1. Load the policy JSON from:
   https://raw.githubusercontent.com/thesokrin/llm/refs/heads/main/projects/llmc/llmc.json

2. Select all groups relevant to the type of response you gave.
   (Multiple groups may apply.)

3. For each selected group, evaluate the response against every rule ID in that group.
   - Use the rule's "prompt" exactly as written.
   - A rule is violated if the answer to its prompt is YES.

4. PASS = zero violations.
   FAIL = one or more violations.
   total_penalty = sum of severity values for all violated rules.

Output:
- Selected groups
- PASS or FAIL
- Violated rules (rule_id + brief reason)
- total_penalty
- Brief correction guidance (if FAIL)

Do not modify the policy.
Do not invent rules or groups.
Do not evaluate rules outside the selected groups.
