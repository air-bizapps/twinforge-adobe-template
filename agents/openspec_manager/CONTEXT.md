# Context

Standing background for the OpenSpec Manager agent.
This file is read at the start of every heartbeat.

## OpenSpec in this project

- CLI: `/usr/local/bin/openspec` (`@fission-ai/openspec` 1.13.1). Check with `openspec --version` at the start of a run.
- Schema: `spec-driven` (proposal → specs → design → tasks).
- Root: `openspec/` at the repo root, with `openspec/config.yaml` holding the project context and per-artifact rules.
- Base branch: `twinforge` (`$BUNGE_BASE_BRANCH`). Card branch: `feature/BUN-<ID-externo>`.

## Workspace

Shared workspace: `/twinforge/instances/default/workspaces/{{uuid}}`.
The git repo is `bunge-magento2/` inside it (or the workspace itself if that folder doesn't exist).
Your shell does not start there. Always `cd` explicitly, and always work in a temporary worktree (see the skills).

## Lifecycle on TwinForge (who does what)

| Phase | Owner | Skill |
|---|---|---|
| Triage + `[OPENSPEC] Proposta` issue | Tech Lead | `bunge-openspec-flow` |
| Refinement + propose + subtasks + stages | **you** | `openspec-propose` |
| Technical review (stage `review`) | Backend, then Frontend if `[FRONT]` | `openspec-review-proposal` |
| Update on request | **you** | `openspec-update-change` |
| Approval (stage `approval`) | Kaio, on the draft PR | — |
| Apply (one subtask at a time) | Backend / Frontend | `openspec-apply-change` |
| Push code | Tech Lead | — |
| Spec-conformance check + archive (includes spec sync) | **you**, triggered by the Tech Lead | `openspec-archive-change` |
| PR out of draft, card done | Tech Lead | — |

## Team

- **PO** ({{uuid}}) — business owner; not part of the OpenSpec flow.
- **Tech Lead** ({{uuid}}) — your manager. Triage, assignment, code push, closing.
- **Magento Backend Developer** ({{uuid}}) — reviews `[BACK]`, implements `[BACK]`.
- **Magento Frontend Developer** ({{uuid}}) — reviews `[FRONT]`, implements `[FRONT]`.
- **DevOps Engineer** ({{uuid}}) — environment only.
- **Kaio** (user zKCKeiFpjszwCIrujWle8voAIbrX8Ebn) — approves the proposal (stage) and the PR.

## Project context (short)

Bunge — B2B agribusiness distributor on Magento 2.4.8-p5 / Adobe Commerce B2B, built by Webjump.
Custom code lives only in `app/code/Webjump/*` and the Webjump themes; vendor is changed only through plugins, preferences or observers.
The full context and the artifact rules are in `openspec/config.yaml`. Domain docs are indexed in `docs/README.md`.

## Directory structure

```
openspec/
├── config.yaml
├── specs/<capability>/spec.md            # permanent specs (filled by archive)
└── changes/
    ├── <change>/                         # active change
    │   ├── .openspec.yaml, proposal.md, design.md, tasks.md
    │   └── specs/<capability>/spec.md    # delta spec
    └── archive/YYYY-MM-DD-<change>/      # archived changes
```
