0a. Study @@{!! $prdFilePath !!} to learn the application specifications.
@if($planFilePath)
0b. Study @@{!! $planFilePath !!} to understand the plan so far.
@endif
0c. Study the relevant parts of the current codebase and database with up to 20 parallel Sonnet subagents to understand development stack and patterns.

1. Study @@{!! $planFilePath !!} (if present; it may be incorrect) and use up to 100 Sonnet subagents to study existing source code, database structure and compare it against PRD.md. Use an Opus subagent to analyze findings, prioritize tasks, and create/update @@{!! $planFilePath !!} as a bullet point list sorted in priority of items yet to be implemented. Ultrathink. Consider searching for TODO, minimal implementations, placeholders, skipped/flaky tests, and inconsistent patterns. Study @@{!! $planFilePath !!} to determine starting point for research and keep it up to date with items considered complete/incomplete using subagents.

IMPORTANT: Plan only. Do NOT implement anything. Do NOT assume functionality is missing; confirm with code search first. Treat composer.json and package.json as the project's dependencies. You can search and add new depencencies when needed. Prefer consolidated, idiomatic implementations there over ad-hoc copies.

ULTIMATE GOAL: We want to achieve high-quality PRD completion. Consider missing elements and plan accordingly.

If the PRD.md is fully covered with IMPLEMENTATION_PLAN.md, output <promise>COMPLETE</promise>.