# AI Coding Workflow: Make Every Token Count

Goal: make every prompt, token, and minute useful. The workflow does this by keeping AI output short, aligning before coding, slicing work vertically, using tests as feedback, and resetting context before it gets noisy.

## Core idea

Do not start with code. Start by forcing clarity.

Recommended loop:

```text
/grill-me or /grill-with-docs
  -> /to-prd
  -> /to-issues
  -> /tdd
  -> manual review
  -> repeat or /handoff
```

Run `/setup-matt-pocock-skills` once per repo before relying on the engineering skills.

## Glossary

**Skill**: A reusable AI workflow command. A good skill tells the agent how to think, what steps to follow, and what output to produce.

**Alignment**: The process of making your intent, constraints, vocabulary, and success criteria clear before the agent writes code.

**PRD**: Product Requirements Document. A short document describing the problem, solution, user stories, implementation decisions, testing decisions, and out-of-scope work.

**Vertical slice / tracer bullet**: A small end-to-end piece of value. It should cross the real layers needed for the feature, such as schema, API, UI, and tests. Avoid horizontal tickets like "build database", then "build API", then "build UI".

**TDD**: Test-driven development. Write one failing behavior test, implement the minimum code to pass it, then refactor after tests are green.

**Smart zone**: The part of a conversation where the model is most likely to follow instructions and keep the right context. As context grows, accuracy usually gets worse. Treat any exact percentage as a rough mental model, not a law.

**Handoff**: A short summary document for a fresh agent/session. It preserves only the useful state, instead of carrying the entire conversation forward.

## 1. Token control with Caveman

Use Caveman when you want concise, technical AI output with less filler.

Best uses:

- Shorter explanations.
- Concise code reviews.
- Cleaner commit messages.
- Lower context waste in long sessions.

Commands may vary by tool, so check the slash-command list in your environment. Common commands include:

```text
/caveman
/caveman-commit
/caveman-review
```

Installation depends on which tool you use:

```sh
# Caveman skill
npx skills add JuliusBrussee/caveman

# Caveman Code terminal agent
npm install -g @juliusbrussee/caveman-code
```

Warning: concise output is not the same as guaranteed quality. Use it for focus and speed, but still review important technical decisions, security changes, and production code.

## 2. Alignment: `/grill-me` and `/grill-with-docs`

Use `/grill-me` before building anything unclear. It makes the agent interview you until the plan, tradeoffs, constraints, and edge cases are understood.

Use `/grill-with-docs` when project language matters. It also helps build or update domain docs such as glossary, context notes, and ADRs.

Why it matters:

- Fewer wrong assumptions.
- Better shared vocabulary.
- Better chat history for later steps.
- Less rework during coding.

Warning: grilling gets long when the request is vague or too big. Narrow the task first when possible. Answer carefully, because these answers shape the PRD, issues, and implementation.

## 3. Planning: `/to-prd`

Use `/to-prd` after a grilling session. Its job is to turn the current conversation into a PRD, not start a new interview.

The PRD should explain:

- The user's problem.
- The intended solution.
- User stories.
- Important implementation decisions.
- Testing decisions.
- Out-of-scope work.

Warning: AI is good at summarizing, but not perfect. Skim the PRD for wrong assumptions, missing constraints, risky scope, and outdated details before moving on.

## 4. Work breakdown: `/to-issues`

Use `/to-issues` on the PRD or plan. It should create small, independently grabbable issues using vertical slices.

A good issue:

- Produces a narrow but complete behavior.
- Is demoable or verifiable on its own.
- Has clear acceptance criteria.
- Lists blockers or dependencies.
- Uses the project's domain language.

Warning: avoid horizontal slicing. Do not create one issue for all database work, another for all backend work, and another for all frontend work. That delays feedback and increases integration bugs.

## 5. Coding: `/tdd`

Use `/tdd` to implement one issue at a time with a red-green-refactor loop.

Correct loop:

```text
1. Write one failing behavior test.
2. Write the minimum code to pass it.
3. Repeat for the next behavior.
4. Refactor only after tests are green.
```

Good tests verify public behavior, not private implementation details. They should survive refactors.

Warning: only parallelize independent issues. Do not run multiple agents on overlapping files or coupled features unless you are ready to resolve conflicts carefully.

## 6. Manual review

Manual review is mandatory. Do not skip it because tests passed.

Review checklist:

- Read every AI-generated diff.
- Run tests, type checks, lint, and formatting.
- Check security, auth, data handling, and migrations carefully.
- Remove debug logs and throwaway files.
- Make sure the code matches the issue and PRD, not just the tests.

Warning: AFK coding can produce useful drafts, but merging remains a human responsibility.

## 7. Debugging: `/diagnosing-bugs`

Use `/diagnosing-bugs` for hard bugs, flaky failures, or performance regressions.

Diagnosis loop:

```text
reproduce -> minimize -> hypothesize -> instrument -> fix -> regression test -> cleanup
```

The most important step is building a tight feedback loop: one command or test that reliably shows the bug and can turn green after the fix.

Warning: do not guess before you can reproduce. If no loop exists, list what you tried and ask for logs, traces, a recording, or access to the environment that reproduces the bug.

## 8. Context management

Context quality matters. Long conversations can carry old assumptions, stale docs, and irrelevant details.

Use fresh sessions when:

- The task changes.
- The conversation becomes too long.
- The agent starts missing instructions.
- Old decisions are no longer relevant.

### Compacting

Compacting summarizes the current conversation so the session can continue. It is useful when you cannot restart, but the summary still takes context and may preserve old assumptions.

### Handoff

Use `/handoff` when a fresh session should continue the work. The handoff should live in a temporary directory, not the project workspace, unless you intentionally want it versioned.

A good handoff includes:

- Current goal.
- Completed work.
- Relevant files, issues, PRDs, ADRs, and commands.
- Known risks or open questions.
- Suggested next skills.
- No secrets or sensitive data.

## 9. Codebase hygiene tips

Keep the workspace clean so the agent does not learn from stale artifacts.

Clean or archive:

- Finished PRDs.
- Finished issue drafts.
- Old plans.
- Debug scripts.
- Prototype files.
- Outdated architecture notes.

Use `/improve-codebase-architecture` when the system feels harder to change, tests are painful, or features require touching too many files.

Use `/prototype` when the design is still unknown. Build something disposable to learn quickly, then delete or clearly mark it as throwaway.

## Final workflow

```text
1. Clarify with /grill-me or /grill-with-docs.
2. Summarize into a PRD with /to-prd.
3. Split into vertical issues with /to-issues.
4. Implement each issue with /tdd.
5. Review manually before merge.
6. Use /diagnosing-bugs for hard bugs.
7. Use /handoff or a fresh session when context gets noisy.
```

## Best rule

Use AI for speed, structure, and feedback. Keep humans responsible for judgment, architecture, security, and final review.

## Resources

- Matt Pocock skills: https://github.com/mattpocock/skills
- Full Walkthrough: Workflow for AI Coding - Matt Pocock: https://www.youtube.com/watch?v=-QFHIoCo-Ko
- Caveman skill: https://github.com/JuliusBrussee/caveman
- Caveman Code: https://github.com/JuliusBrussee/caveman-code
