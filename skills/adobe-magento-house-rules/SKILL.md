---
name: magento-house-rules
description: "Personal tuning notes for the Magento Engineer agent, actively maintained by Kaio while forging and improving this agent. Use for known platform-specific quirks and workarounds that don't belong in the general Magento or Bunge-process skills. Expect this file to change often."
---

# Magento Engineer — House Rules

This is the one skill in this agent's stack that changes often. It holds
notes discovered from watching this specific agent run — not general
Magento knowledge (`adobe-commerce-standards`) and not general Bunge process
(`bunge-engineering-practices`).

Platform-quirk and known-bug notes (like the former BUN-7 issue reopen-loop
entry) now live in the `engineering-memory` system instead of here — see
`.agent-memory/MEMORY.md` in the workspace and the `engineering-memory`
skill for how it works. Check that index whenever a situation feels like a
repeat before re-deriving a diagnosis. This file stays for personal tuning
notes that are about *this agent's behavior*, not about the codebase or
platform's known quirks.

## Environment state

The DevOps Engineer prepared this environment. Check with them (or
`engineering-memory`, if a prior run recorded a finding) before re-deriving
environment readiness from scratch on every task.

## How to propose a change to this file

If you notice a durable pattern worth remembering — a recurring mistake, a
workaround that worked, a preference Kaio stated — say so explicitly in your
task comment and propose the exact addition. Do not edit this skill file
yourself; skill content changes go through Kaio (or a dedicated
skill-quality review), per the standing rule that instruction changes are
not bundled into ordinary code-change tasks.
