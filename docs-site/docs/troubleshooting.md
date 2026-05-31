# Troubleshooting

## I Cannot See The Project

Check that:

- you are signed into the correct account;
- the active workspace is the lab that owns the project;
- you have project membership or workspace admin access.

## I Can See A Page But Cannot Edit It

You may have reviewer or viewer access. Some actions are intentionally limited
to owners, admins, or adjudicators.

## Search Or Retrieval Says It Is Queued

Search and full-text retrieval run in the background. A queued state means the
request was accepted but work has not finished yet.

## Search Results Look Noisy

That is normal. Provider search is broad by design, and the team narrows the
corpus through inspection, deduplication, and screening. If known anchor
records are missing, revise the search before locking the corpus.

## Full Text Failed For A Record

This is normal. Legal open-access retrieval is incomplete by nature. Treat
failures as audit facts and follow the protocol for manual follow-up.

## A Record Appears More Than Once

Open deduplication and inspect duplicate clusters. Records from multiple
providers may represent the same study.

## A Reviewer Disagreed With Another Reviewer

Open the conflict panel. An adjudicator should compare both rationales, record
the final decision, and enter an audit reason.

## I Need Data Extraction Or PRISMA Exports

Those workflows are planned after the current MVP. The implemented tutorial
ends at full-text eligibility handoff.
