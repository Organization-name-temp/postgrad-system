# Issue tracker: Local Markdown

Issues and specs (a spec is what you may know as a PRD) for this repo live as markdown
files under `.scratch/`. This repo has a GitHub remote
(`MohamedYasser343/postgrad-system`), but the `gh` CLI is not installed and this is a
solo localhost learning build, so work is tracked locally rather than in GitHub Issues.
Switch to GitHub later by installing + authenticating `gh` and updating this file.

## Conventions

- One feature/effort per directory: `.scratch/<feature-slug>/`
- The spec is `.scratch/<feature-slug>/spec.md`
- Implementation issues are one file per ticket at
  `.scratch/<feature-slug>/issues/<NN>-<slug>.md`, numbered from `01` — never a single
  combined tickets file
- Triage state is recorded as a `Status:` line near the top of each file
- Comments/conversation append to the bottom under a `## Comments` heading

## Triage label vocabulary

The five canonical roles, used as `Status:` values: `needs-triage`, `needs-info`,
`ready-for-agent` (fully specified, ready for an agent), `ready-for-human`, `wontfix`.

## When a skill says "publish to the issue tracker"

Create a new file under `.scratch/<feature-slug>/` (creating the directory if needed).

## When a skill says "fetch the relevant ticket"

Read the file at the referenced path; the user normally passes the path or issue number.
