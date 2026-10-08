# local_reflectiveassessment

Reflective Assessment reporting, selectable by batch and semester. Lives at
**Site administration → Reports → Reflective Assessment: guide summary**
(`/local/reflectiveassessment/index.php`). Every view downloads as
CSV/Excel/ODS/JSON via the standard dataformat selector.

Three views:

| View | Rows | Answers |
|---|---|---|
| **Guide summary** | one per guide course | how far has each guide's cohort got |
| **Who evaluated** | one per person who marked | who did the marking, and how much |
| **Student detail** | one per student per guide course | the per-student record |

## What a Reflective Assessment is, structurally

It is not an activity inside a taught course. Each guide gets a dedicated course
— full name `Reflective Assessments - <guide>`, shortname `RS-<guide>-<program>`
— holding an Announcements forum and a single quiz named `Reflective
Assessment`. That quiz is one essay question marked out of 25, one attempt,
deferred feedback. The students allocated to a guide are enrolled into that
guide's course, and the guide marks the essays manually.

Guide courses sit in the semester category of their programme, so the category
chain is `<batch> › <programme> › <semester>` and the report reads batch and
programme by walking that path from the right. Nothing is hard-coded per intake:
a new batch's guide courses appear in the period selector as soon as they exist.

## Why progress is read from the question state, not the gradebook

A student can finish the quiz without writing anything. Moodle auto-scores that
zero immediately, so in the gradebook it is indistinguishable from a guide
awarding zero — and it counts as "graded" although no guide has ever seen it. In
July 2025 Semester I that is **711 of 5,613 submissions**, so the distinction is
not marginal.

The report therefore classifies each attempt by the final state of its question:

| Final state | Bucket | Meaning |
|---|---|---|
| `mangr*`, `mangaveup` | Evaluated | a guide entered a mark |
| `needsgrading` | Awaiting evaluation | answered, nobody has marked it |
| `gaveup` and anything else | Submitted blank | finished with no answer |

From which: `Submitted` = Evaluated + Awaiting + Blank (every finished attempt),
and `Answered` = Submitted − Blank (what actually needs a guide's attention).

Each attempt is reduced to its **first** question (`MIN(question_attempts.id)`
per usage), so if the assessment ever grows a second question the report still
yields one row per student rather than double-counting.

## Guide vs evaluator

They are reported in separate columns because they genuinely differ.

- **Guide** = the course's own teacher-archetype role holder, after removing the
  service accounts (shared per-programme `DSM` mailboxes, content team, site
  admin — configurable in settings). Where a course carries several teachers,
  the one who has actually marked wins; the rest are observers.
- **Evaluated by** = who authored the most recent manual grading step on each
  attempt, so a re-mark counts once and lands with whoever marked it last.

Two fallbacks matter:

1. **Deleted guide accounts.** Deleting a user drops their role assignments, so
   two July-2025 guides (Lavanya Sridharan, Anand Bharathi) hold no role in their
   own course. The marking history survives the account, so the guide falls back
   to the top non-service evaluator and the row is flagged `Deleted`.
2. **Shared-account marking.** 150 July-2025 evaluations were entered from `DSM`
   mailboxes rather than by the named faculty member (86 in RS-PP-MCA, 55 in
   RS-MRCS-MCA, 7 and 2 in two MA courses). Those rows are flagged and the
   breakdown names the shared account explicitly.

Do not infer the guide from the course shortname: `RS-MRCS-BBA` is Chethana K,
`RS-MRCS-Bcom` is Abdullah Malik and `RS-MRCS-MCom` is Sonika Seth. The
shortnames are wrong; the role assignments are right.

## Columns

`Batch` · `Program` · `Semester` · `Guide` · `Guide Email` · `Guide Account` ·
`Course Code` · `Allocated` · `Submitted` · `Answered` · `Evaluated` ·
`Awaiting Evaluation` · `Submitted Blank` · `Not Attempted` · `% Submitted` ·
`% Evaluated` · `Avg Mark` · `Max Mark` · `Evaluated By` · `Last Evaluated`

`Allocated` counts distinct non-deleted student-archetype role holders in the
guide course. `% Submitted` is Submitted/Allocated; `% Evaluated` is
Evaluated/Submitted. `Avg Mark` averages the evaluated attempts only — blanks are
excluded so an unmarked zero cannot drag a guide's average down. Totals
recompute their percentages from the totals rather than averaging the rows.

## Scope

The batch/semester selector is limited by the **Batches to report on** setting,
which ships as `17` — July, 2025 Batch only. Jan 2026 and Jan 2026 - TP guide
courses exist and the code handles them unchanged; they are simply not offered
until their category id is added to that setting. Clearing the setting shows
every batch that has guide courses.

The student's essay text and the guide's feedback comment are deliberately not
reported.

## One row per student, not per attempt

Every count is a count of allocated students. Two things would otherwise inflate
it, and both are filtered in SQL:

- **Repeat attempts.** The quiz allows one attempt, but 17 students hold more
  than one finished attempt (19 extra in total). Only each student's latest
  finished attempt counts.
- **Attempts that outlived the allocation.** Attempts survive unenrolment and
  user deletion. Two July-2025 attempts belong to people who no longer hold a
  student role - one unenrolled, one a deleted account.

So for July 2025 Semester I: 5,613 finished attempts = 5,592 students + 19
repeats + 2 non-allocated. Without this, `% Submitted` would divide submissions
by students and could exceed 100%.

The same restriction applies to the evaluator rollup, so all three views agree.

## Verified against production

Built for July 2025 Semester I (52 guide courses) on 2026-08-14. All three views
reconcile exactly and the arithmetic identities hold:

```
Allocated 8,913 · Submitted 5,592 · Answered 4,892 · Evaluated 4,702
Awaiting 190 · Blank 700 · Not attempted 3,321 · 63% submitted · 84% evaluated

detail rows == allocated · detail per-status == summary per-status
evaluator graded == summary evaluated · eval+await+blank == submitted
submitted + not attempted == allocated
```

47 evaluators, 150 evaluations from shared accounts. All 52 guides resolve (50
from the teacher role, 2 via the deleted-account fallback). Jan 2026 Batch (29
courses) and Jan 2026 Batch - TP (2 courses) build from the same code with no
changes. Build times: guide summary ~0.6s, evaluators ~0.3s, student detail
~1.3s for 8,913 rows.

## Notes

- `local/reflectiveassessment:view` is manager-only by default; the page is
  registered under the Reports category with that capability.
- Adding the capability needs `admin/cli/upgrade.php` after deploy — CI never
  runs the Moodle DB upgrade.
- Marking continues well past the quiz close date (16 Mar 2026): the latest
  July-2025 evaluation is 20 Jul 2026. Any published figure needs the as-at
  stamp the page prints under the table.
