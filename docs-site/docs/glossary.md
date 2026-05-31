# Glossary

## Adjudication

The process of resolving a disagreement between reviewers. The final decision
and audit reason are retained.

## Audit Reason

A short explanation recorded when someone performs an important action, such as
locking a corpus or resolving a conflict.

## Corpus

The set of records collected for a project. Before deduplication it may include
multiple records that point to the same study.

## Deduplication

The process of identifying records that likely represent the same study and
choosing one representative record for downstream review.

## Full-Text Artifact

A PDF, XML, or text artifact retrieved from a legal open-access source.

## Full-Text Screening

The final eligibility decision made after reviewers inspect the full text.

## Locked Snapshot

A frozen set of representative records. Screening and later workflows use this
snapshot so the decision set does not change underneath the team.

## Maybe

A decision meaning the reviewer cannot make a final decision yet. In the data
model this maps to `needs_review`.

## Protocol

The review plan: question, eligibility criteria, search policy, language policy,
reviewer policy, AI policy, and full-text policy.

## Provider

A source searched by Nexus Scholar, such as OpenAlex, Crossref, PubMed,
Semantic Scholar, arXiv, DOAJ, or another configured source.

## Query Link

The relationship between a record, the search query that found it, and the
provider that returned it.

## Screening Conflict

A disagreement between reviewers that requires adjudication.

## Workflow Handoff

The point where one workflow produces a stable input for the next workflow,
such as title-and-abstract screening producing candidates for full-text
retrieval.
