# Research Handoff Checklist

Use this checklist when a student, lab, or research team is ready to turn the
tutorial workflow into a real review project.

The goal is simple: do not let the software make the review feel more rigorous
than the protocol actually is. Nexus Scholar can preserve decisions, counts,
and audit trails. The team is still responsible for the scientific method.

## 1. Decide What Kind Of Review This Is

Before opening the project, write down the review type and the expected output.

| Decision | What to record |
| --- | --- |
| Review type | Systematic review, scoping review, living review, evidence map, thesis review, or another defined format. |
| Intended output | Thesis chapter, internal evidence brief, preprint, journal article, guideline support, or grant background. |
| Registration plan | PROSPERO, OSF Registries, institutional protocol approval, or a clear reason registration is not used. |
| Reporting standard | PRISMA 2020 for systematic reviews, PRISMA-S for search reporting, and any field-specific extension your team needs. |

!!! warning "Do not skip this"
    A broad literature search is not automatically a systematic review. The
    protocol, search strategy, eligibility criteria, screening process, and
    reporting plan determine what claim the team can make.

## 2. Assign The Review Team

Every real review should have named responsibility before screening starts.

| Role | Required decision |
| --- | --- |
| Review lead | Owns the protocol, lock decisions, and final handoffs. |
| Method lead | Checks review type, eligibility criteria, search transparency, and reporting plan. |
| Search reviewer or librarian | Reviews provider choices, query structure, search dates, and update plan. |
| Reviewers | Screen records independently and record rationales. |
| Adjudicator | Resolves conflicts with an audit reason. |
| Supervisor or PI | Approves scope, risk level, and claims before dissemination. |

If the project has only one reviewer, treat it as an exploratory review or
training exercise unless your field and supervisor explicitly approve a
single-reviewer method.

## 3. Freeze The Protocol Before Search

The project protocol should be good enough that another reviewer can apply it
without asking what the team meant.

Minimum fields:

- research question;
- population, concept or intervention, comparator if relevant, and outcomes;
- included and excluded study designs;
- date limits and language policy;
- databases or providers;
- search update plan;
- reviewer count and conflict policy;
- AI policy;
- full-text policy;
- planned downstream steps after full-text screening.

Use cautious wording. For example, write "evaluate whether digital coaching
interventions are associated with improved outcomes" instead of "prove digital
coaching works."

## 4. Validate The Search Strategy

Before locking the corpus, check the search in front of the team.

| Check | Why it matters |
| --- | --- |
| Known relevant records appear | Anchor records reveal whether the strategy can find expected evidence. |
| Provider mix is justified | PubMed, OpenAlex, Crossref, and discipline databases cover different evidence surfaces. |
| Query strings are retained | Search reporting needs the exact strings, not only the topic. |
| Dates are explicit | Search date and date limits affect reproducibility. |
| Noise is discussed | Irrelevant records can be acceptable if the strategy has good sensitivity. |
| Missing sources are named | Some reviews need Embase, Scopus, Web of Science, IEEE, PsycINFO, CINAHL, or grey literature. |

Do not lock the corpus if the team cannot explain why the selected providers
are sufficient for the review claim.

## 5. Run A Calibration Round

Before full screening, give each reviewer the same small set of records.

Recommended calibration:

1. Choose 10 to 25 mixed records from the draft or locked corpus.
2. Ask reviewers to screen independently.
3. Compare disagreements.
4. Update the protocol only if the criteria were ambiguous.
5. Record what changed and why.

Calibration is not about forcing agreement. It is about making sure reviewers
understand the same criteria before the full batch begins.

## 6. Define AI Boundaries

If AI assistance is used, record it before the model touches the review.

| AI decision | What to document |
| --- | --- |
| Allowed tasks | Search-string brainstorming, rationale drafting, title/abstract triage, extraction assistance, or none. |
| Not allowed tasks | Final inclusion decisions, unsupervised exclusions, invented citations, or unsupported scientific conclusions. |
| Model and provider | Name the model, provider, date, and configuration when practical. |
| Human review | State who checks AI-supported outputs and how overrides are recorded. |
| Retention policy | Decide whether prompts, raw responses, and model rationales are stored. |

For high-stakes biomedical reviews, keep final eligibility decisions human-led
unless the protocol and ethics of the project explicitly justify otherwise.

## 7. Lock With A Real Audit Reason

The lock reason should be useful months later.

Use the [Before You Lock The Corpus](before-locking-corpus.md) checklist for a
short pass/fail review before the lock is recorded.

Good:

`Corpus locked after search strategy review by Dr. A and librarian B on 2026-06-04. Queries, provider counts, anchor records, and deduplication output were reviewed.`

Weak:

`Ready`

After locking, do not change the protocol or search scope casually. If the
protocol changes, record the amendment and decide whether the search must be
rerun.

## 8. Prepare Full-Text Follow-Up

Automatic retrieval will miss some records. Decide how the team will handle
failures.

| Item | Team decision |
| --- | --- |
| Institutional access | Who checks university subscriptions or interlibrary loan? |
| Author contact | When will the team contact authors? |
| Exclusion timing | Will records be excluded only after full-text access fails, or carried as unavailable? |
| Failed artifacts | Who reviews failed downloads and source errors? |
| Final reasons | Which full-text exclusion reasons will appear in the final report? |

## 9. Stop And Review Before Dissemination

Before using results in a thesis, paper, policy brief, or grant:

- confirm the protocol version and amendments;
- export or preserve search queries, provider counts, and run dates;
- review include, maybe, exclude, and conflict decisions;
- verify full-text exclusion reasons;
- confirm whether extraction and risk-of-bias appraisal are still required;
- decide what claims the evidence actually supports;
- have the PI, supervisor, or method lead approve the wording.

## Go / No-Go Summary

| Status | Meaning |
| --- | --- |
| Go | Protocol approved, search validated, reviewers assigned, AI policy recorded, corpus locked with a clear reason, and screening audit trail retained. |
| Conditional go | The project can proceed internally, but missing sources, single-reviewer screening, or full-text limitations must be disclosed. |
| No-go | The question is unclear, search strategy is not validated, criteria are unstable, or the team wants to make causal claims the evidence cannot support. |

## References

- [Cochrane Handbook for Systematic Reviews of Interventions](https://www.cochrane.org/authors/handbooks-and-manuals/handbook)
- [Cochrane guidance on writing a protocol](https://www.cochrane.org/authors/how-write-cochrane-protocol)
- [PRISMA 2020 statement](https://www.prisma-statement.org/prisma-2020)
- [PRISMA-S extension](https://www.prisma-statement.org/prisma-search)
- [PROSPERO](https://www.crd.york.ac.uk/prospero/)
- [OSF Registries](https://osf.io/registries)
