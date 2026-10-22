## Multi-model workflow

- Use the Sonnet main session for coordination, coding and verification.
- Delegate focused repository exploration to the `search` subagent.
- Delegate symbol and call-site lookup to the `grep` subagent.
- Delegate local documentation lookup to the `docs` subagent.
- Delegate independent read-only research in parallel when useful.
- Keep subagent reports concise and include paths/references.
- Avoid delegation for trivial tasks where setup overhead exceeds its value.

## Advisor policy

For substantive tasks, consult the Opus advisor:
1. Before committing to a complex or risky implementation plan.
2. After the same failure recurs twice, before repeating the same approach.
3. Before declaring a substantial change complete, after running checks.

For minor text or formatting edits, avoid unnecessary advisor calls.
Do not claim the advisor was consulted unless the consultation actually occurred.

## Verification and permissions

- Run relevant tests or checks when available.
- Never claim a test passed unless it was actually executed.
- Explain verification gaps and remaining risks.
- For major or destructive actions, show the plan/diff and request approval.