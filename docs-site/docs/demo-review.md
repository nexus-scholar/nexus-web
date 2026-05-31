# Real Evidence Example

The tutorial follows one real evidence-review topic:

**Digital health interventions for cardiometabolic risk management in primary
care.**

The example was created in Nexus Scholar on May 31, 2026. Searches were run
against OpenAlex, Crossref, and PubMed. The resulting corpus includes real
scholarly records, including relevant papers and irrelevant records that a
review team would need to exclude.

!!! warning "Not a completed review"
    The search results are real. The screening decisions are instructional
    decisions used to demonstrate the workflow. Do not cite the tutorial counts
    as a clinical or scientific conclusion.

## Review Question

Among adults in primary care with cardiovascular disease, type 2 diabetes, or
cardiometabolic risk factors, do patient-facing digital health coaching, apps,
telehealth coaching, text messaging, or EHR-linked feedback tools improve
medication adherence, physical activity, blood pressure, LDL cholesterol,
HbA1c, weight, quality of life, or healthcare utilization compared with usual
care or non-digital support?

## Eligibility Frame

| Element | Protocol value |
| --- | --- |
| Population | Adults in primary care, family medicine, community primary care, or closely related outpatient chronic-disease settings with cardiovascular disease, cardiovascular risk, hypertension, type 2 diabetes, metabolic syndrome, obesity, or cardiometabolic risk factors. |
| Intervention | Patient-facing digital health coaching, mobile or web apps, telehealth coaching, text messaging, remote self-management support, or EHR-linked feedback tools. |
| Comparator | Usual care, non-digital support, delayed intervention, or another active comparator. |
| Outcomes | Medication adherence, physical activity, blood pressure, LDL cholesterol, HbA1c, weight, quality of life, utilization, or implementation outcomes. |
| Designs | Systematic reviews, meta-analyses, randomized trials, cluster randomized trials, and controlled implementation studies. |
| Date range | 2020 to May 31, 2026 for this tutorial. |
| Language policy | English-language records for the walkthrough. Non-English records should be logged for translation review in a full review. |

## Search Strategy Used

The tutorial uses broad discovery queries and targeted anchor queries. Anchor
queries help confirm that known relevant studies are findable by the configured
providers.

| Query label | Search string |
| --- | --- |
| Digital CVD primary care | `digital health primary care cardiovascular risk randomized trial` |
| Diabetes digital coaching | `digital coaching type 2 diabetes primary care systematic review` |
| Telehealth cardiometabolic coaching | `telehealth coaching cardiometabolic risk primary care` |
| CONNECT trial anchor | `CONNECT randomized controlled trial primary care electronic health record cardiovascular disease digital health` |
| Digital coaching diabetes review anchor | `Digital Coaching Strategies to Facilitate Behavioral Change in Type 2 Diabetes systematic review` |
| Primary care DHI review anchor | `Effectiveness of digital health interventions chronic conditions management primary care systematic review meta-analysis` |

## Results Captured In The App

<div class="metric-row" markdown>

<div class="workflow-card" markdown>
**Search run**

101 raw results, 85 unique item-level results, 0 provider failures.
</div>

<div class="workflow-card" markdown>
**Locked corpus**

72 representative records, 141 query links, 3 providers.
</div>

<div class="workflow-card" markdown>
**Title and abstract**

11 include, 3 maybe, 58 exclude training outcomes.
</div>

<div class="workflow-card" markdown>
**Full text**

14 candidates, 6 retrieved artifacts, 8 failed retrievals.
</div>

<div class="workflow-card" markdown>
**Full-text eligibility**

4 include, 1 maybe, 1 exclude, 1 resolved conflict.
</div>

</div>

## Representative Records

These records were present in the live corpus and are useful anchors for the
walkthrough:

| Record | Why it matters in the tutorial |
| --- | --- |
| Redfern et al., 2020, **CONNECT randomized controlled trial** | A real primary-care digital health RCT for cardiovascular disease management. The trial found no clear medication-adherence improvement, with some behavior and risk-factor signals. |
| Gershkowitz et al., 2020, **Digital Coaching Strategies to Facilitate Behavioral Change in Type 2 Diabetes** | A review record that fits the digital coaching and diabetes part of the question. |
| Ambrosi et al., 2025, **Digital health interventions for chronic conditions management in European primary care settings** | A primary-care systematic review/meta-analysis that should be handled cautiously because effects vary by outcome. |
| Digital interventions for self-management of type 2 diabetes, 2024 | A recent systematic review/meta-analysis record found in the corpus. |
| Digital health interventions for lipid management in atherosclerotic cardiovascular disease, 2026 | A recent review record used in full-text retrieval and eligibility screening. |

## Scientific Interpretation

The example is intentionally realistic. The search returns useful records,
protocols, guidelines, unrelated clinical records, and provider noise. That is
normal in evidence synthesis. Nexus Scholar helps the team keep the search,
screening, full-text retrieval, and conflict-resolution trail visible.

The safe scientific position for this topic is cautious: digital health and
coaching interventions may improve some behavioral or process outcomes in some
contexts, but evidence for durable clinical outcome improvement is mixed and
depends on the population, intervention design, comparator, and setting.
