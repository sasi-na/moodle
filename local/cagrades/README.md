# local_cagrades — Continuous assessment reporting and gradebook schemes

Two things live here: a **report source** for continuous assessment marks, and the **CLI tools** that
put this site's marking rules into the gradebook itself so nobody works them out by hand.

| Tool | Rule |
|---|---|
| `cli/apply_ca_category.php` | Taught courses: best **two of three** CAs, averaged, out of 30 |
| `cli/apply_lab_scheme.php` | Virtual Labs: best **one of two** per section, four components out of 100, with a 35-to-40 moderation floor |

Both express their rule as native grade categories rather than custom grading code, and both verify
what core stored against the rule for every learner before they will call a course done.

## Continuous assessment report source

Adds a **Continuous assessment grades** report source to Moodle's custom report builder
(*Site administration → Reports → Report builder → Custom reports → New report*). Core report
builder ships no gradebook source, so CA marks cannot otherwise be reported on there at all.

One row per **student per course**, carrying that student's CA grades.

## Columns

| Column | Meaning |
|---|---|
| `CA 1`, `CA 2`, `CA 3` | The student's grade for that continuous assessment |
| `CA 1/2/3 maximum` | What that CA is graded out of (a property of the course, shown even when the student is ungraded) |
| `CA total`, `CA total maximum` | Sum of CA 1–3, `NULL` when the student has no CA grade at all |
| `CA percentage` | `CA total / CA total maximum`, computed in PHP so it costs no extra SQL |
| `CA best two total` | The two highest CA grades added together — the "30 + 28" of the rule below |
| `CA final mark` | The best two of three, averaged, out of what a single CA is marked out of |
| `CAs graded` | How many of CA 1–3 the student has a grade for (0–3) |
| `CA quizzes in course` | How many CA quizzes the course holds — per course, not per student |
| `CA quiz names` | The names of those quizzes, for auditing |
| `Reflective Assessment` (+ `maximum`, `course`) | The student's mark from the separate faculty-wise Reflective Assessment course |
| `CA 1/2/3 (weighted to 25)` | That CA weighted down to what it is worth in the AI Skills scheme |
| `AI Skills total` (+ `maximum`) | The four weighted components added up, out of 100 |

## The best-two-of-three rule

The final CA mark is the two highest CAs averaged: with CA 1 = 28, CA 2 = 30 and CA 3 = 25, the best
two are 30 and 28, and the mark is `(30 + 28) / 2 = 29` out of 30. An **ungraded CA counts as zero**,
which is what keeps the divisor at two — a student with only CA 1 = 28 scores 14, not 28.

`local_cagrades\ca_items` owns that rule, and both this report and
`cli/apply_ca_category.php` use it, so the report can never print a mark different from the one in
the student's own gradebook. `ca_items::final_mark()` deliberately mirrors what the gradebook does
for a "Mean of grades" category with *keep the highest 2* and *aggregate only non-empty grades* off:
normalise each grade against its own maximum, keep the two highest, average, scale back up. Verified
against core's own `grade_category::apply_limit_rules()` on 4,804 real students plus the worked
examples — no disagreements.

`CA final mark` is deliberately **empty** where the rule does not apply — a course missing one of the
three CAs (7 such courses), or one whose CAs are not all out of the same maximum (13, mixing 10 and
30). There is no defensible "out of" to average towards, and printing a number nobody can reproduce
is worse than printing nothing. Those are exactly the courses `cli/apply_ca_category.php` refuses, so
the column and the learner's gradebook agree even in saying nothing.

There is also no filter on the final mark — that would need a second, SQL-side copy of the rule, and
two definitions of one number eventually disagree. Filter on `CA best two total` instead; it ranks
students identically wherever the rule applies.

## The Reflective Assessment columns

For AI Skills for Future the four assessment components do not all live in the same course: CA 1–3
are quizzes in `ASFJAN26`, while the Reflective Assessment is run as a set of faculty-wise courses
(`00. Reflective Assessment - <faculty>`) sitting in the same course category, each student allocated
to one of them. Moodle's gradebook cannot aggregate across courses, so a four-component total can
only be assembled in a report — which is what these columns are for.

The scheme gives each of the four components **25 marks, totalling 100** — but the CA quizzes are built
out of **30** in the LMS, so each CA is weighted down before the four are added: 27 out of 30
contributes 22.5. `local_cagrades\aiskills_scheme` holds that, deliberately apart from `ca_items`,
which describes a completely different rule for the taught courses. The quizzes are left at /30 on
purpose — rescaling them would rewrite marks learners have already been shown.

The join is correlated on the reported course's **category** and the reported student, keyed on the
student's `grade_grades` row, which is what identifies the one faculty course they were allocated to.
A student allocated to two of them (19 exist) is shown as `<course> (2 allocations)` rather than
silently reported under one of them.

Keying on `grade_grades` rather than on the enrolment means the Reflective Assessment columns are
blank for a student their faculty course's gradebook has never touched — 1,842 of the 2,215 ASFJAN26
students allocated to a faculty course had a row at the time of writing. Those students have no mark
either, so no mark is lost; what is not shown is *which* faculty course an unmarked student sits in.
Keying on enrolment instead would put a second pair of joins inside the correlated subquery for the
sake of information that is only useful once a mark exists.

It is a **column-level** join, not an entity one: entity joins are applied to every column, so
putting it there would make every existing report pay for it. It only runs when a Reflective
Assessment column or filter is actually selected.

A column-level join must be built **once and memoised**. Report builder de-duplicates joins by exact
string match, and `database::generate_aliases()` mints fresh inner aliases on every call while
`get_table_alias()` keeps the outer alias stable — so rebuilding the join per column yields several
"different" joins all carrying the same alias, and the query dies with `Not unique table/alias`.

The **maximum** does not come from that join. What the Reflective Assessment is marked out of is a
property of the marking scheme, not of the student, so it is read per course category by a second,
uncorrelated join (31 RA courses over 9 categories, 0.002s) that mirrors how the CA maxima are read.
Reading it off the per-student join instead was wrong in a way worth remembering: `grade_grades` rows
are created lazily, so a student nobody had graded yet reported a four-component total "out of 90"
instead of out of 115 — a denominator that varied per student.

Course, category, enrolment and user columns come from the core entities, so course/category names
and every user field — including the custom profile fields (Provisional Enrollment Number, USN,
Cohort/Batch, Elective …) — are available as columns and filters with no code here.

Filters/conditions: numeric on `CA 1–3`, `CA total`, `CAs graded`, `CA quizzes in course`;
`Has any CA grade` (yes/no); free-text on `CA quiz names`.

## How CA grade items are identified

By **name**, because there is no site-wide convention for which quiz holds which CA and no id to key
on. Names are inconsistent across courses — 39 distinct spellings at the time of writing, including
`Continous Assessment - 1`, `Continuous Assessment-01`, `Continious Assessment-02`,
`Continuous Assessmnet 2`, `CONTINUOUS ASSESSMENT 3`.

So the name is normalised in SQL (lower-cased, everything that is not a letter or digit stripped)
and matched against `^cont[a-z]*as+e[a-z]*0*<number>$`. This matches all 39 variants and excludes
`Self Assessment` items, which are practice quizzes carrying no CA marks. Verified against prod: the
pattern selects exactly 1,874 grade items site-wide, all of them `mod`/`quiz`, spread over CA 1, 2
and 3 only (no CA 4+ exists).

**The pattern must not contain `?`** — Moodle counts `?` anywhere in a statement as a bind
placeholder, and report builder joins cannot carry bound parameters — hence `as+e` rather than
`ass?e` to tolerate both `assessment` and `asessment`.

Where a course contains a **duplicate** CA quiz (an unused, ungraded leftover beside the real one,
as in `BRM24VMB0C301` and `BENS23VBB0C303`), `MAX()` per CA number keeps the value deterministic and
stops the duplicate from multiplying rows or double-counting the total. The `CA quiz names` column
still shows both, so the duplicate stays visible.

## Who appears in the report

Users holding a **student-archetype role** in the course, with an active enrolment, excluding deleted
accounts but including suspended ones. That is what makes the numbers agree with the per-course
gradebook export — teachers and other staff do not appear in that export either. Verified: 766 rows
for `TAM24VMB3S303`, matching its Moodle grade export exactly (dropping suspended users would give
640).

## Performance

The per-user grades come from a `LATERAL` join correlated on course **and** user. This matters
because report builder's row-count query wraps the entire select list
(`reportbuilder/classes/table/base_report_table.php:121`), so anything expensive in a column is paid
for every matching row on every page view. Measured on prod:

| Scope | Row count query | Page of 30 |
|---|---|---|
| One course (766 rows) | 0.16s | 0.15s |
| Two categories, default columns (30k rows) | 2.3s | 0.06s |
| Two categories, all 12 CA columns | 3.4s | 5.3s |
| No filter at all (979k rows) | ~42s | ~57s |

An uncorrelated pivot was tried first and rejected: it has to aggregate every CA grade on the site
(~665k rows, ~5s) on every query no matter how tightly the report is filtered, and select-list
sub-queries were worse still (~100s site-wide).

**Always give the report a category or course condition.** Unfiltered it reports every student
enrolment on the site, and that is slow for reasons no amount of tuning fixes.

## Caveats

- **MySQL/MariaDB only** — relies on `LATERAL`, `REGEXP_REPLACE`, `REGEXP_SUBSTR` and
  `GROUP_CONCAT`. The entity throws a clear `coding_exception` on any other database rather than
  failing with cryptic SQL.
- **Name matching drifts.** A genuinely new misspelling that loses the `cont` prefix or the trailing
  number would be missed silently. The `CA quizzes in course` / `CA quiz names` columns exist so this
  is auditable: build a report filtered to `CA quizzes in course` less than 3 to find courses whose
  CA quizzes are missing or misnamed.
- Adding the core **`Enrolment: Role`** column duplicates rows for anyone holding more than one role
  in a course (2 such cases exist in the Jan-2025 MBA/BBA courses). That is core report builder
  behaviour — the core Course participants report does the same.
- CA maxima are read from `grade_items.grademax`, so a course whose quiz is graded out of something
  other than 30 reports its own maximum rather than an assumed one.

## Putting the rule in the gradebook — `cli/apply_ca_category.php`

The report tells staff the final CA mark; the gradebook is what tells *students*, and what feeds
grade exports and the grader report. `cli/apply_ca_category.php` puts the rule there, per course, by
creating a **Continuous Assessment** grade category and moving the three CA quizzes into it:

| Setting | Value |
|---|---|
| Aggregation | Mean of grades |
| Drop the lowest | 1 |
| Aggregate only non-empty grades | off — so an unsat CA counts as zero |
| Category maximum | whatever a single CA is marked out of (30 for all but 8 courses) |

**Drop the lowest 1, not keep the highest 2** — arithmetically the same thing for three CAs, but this
site **forces** `grade_keephigh` to 0 (`grade_keephigh_flag` has bit 1 set), and
`grade_category::apply_forced_settings()` applies that silently from both `insert()` and `update()`. A
category asking for "keep the highest 2" therefore came back averaging all three CAs — (30 + 29 + 0) / 3
= 19.67 where the rule says (30 + 29) / 2 = 29.5. `grade_droplow` is not forced. The script now asserts
its settings survived the write, so a forced setting fails the run instead of producing marks that look
plausible.

Core does the arithmetic from there. Setting the category maximum by hand is safe *because* the
aggregation is not Natural: `grade_category::auto_update_max()` returns early for every other
aggregation, so nothing recomputes it from the children.

```
# what would change, and which courses are refused and why
php local/cagrades/cli/apply_ca_category.php --categories=27,2,17,174,239 --exclude-courses=1378,1490

# apply, in batches, journaling to a backup file
php local/cagrades/cli/apply_ca_category.php --categories=27 --exclude-courses=1378,1490 \
    --execute --backup=/path/ca-jul2024.json --limit=25

# undo
php local/cagrades/cli/apply_ca_category.php --rollback=/path/ca-jul2024.json --execute
```

Dry run unless `--execute`, which also requires `--backup`. Every course is journaled *before* its
reconciliation check runs, so a crash mid-sweep still leaves a usable backup. After each course the
script re-reads every student's stored category total and compares it against `ca_items::final_mark()`,
and **aborts the whole run** on the first disagreement rather than carrying on.

Courses are refused, not guessed at, when the data cannot carry the rule: mixed CA maxima, a
duplicate CA quiz, fewer than three CAs, or a non-zero CA minimum. They are listed by name and reason
in the run report.

Two effects worth knowing before running it:

- **The course total drops.** Three items of 30 become one category of 30, so a course total that
  read 90 reads 30. Safe on this site — no course-completion criterion uses a grade, no CA has a
  `gradepass`, and course totals are already inconsistent across courses — but it is visible in the
  grader report and in the `gradereport_summary` reports.
- **Excluded by design**: `ASFJAN26` and `ASFJAN26-TP`. AI Skills for Future totals all four of its
  components rather than taking the best two, so it must not get the category.

Site setting, optional: *Aggregations available* (`grade_aggregations_visible`) is set to Natural
only, so a course admin cannot hand-create one of these categories. Existing ones stay editable
regardless — core always keeps a category's current aggregation in its own dropdown
(`grade/edit/tree/category_form.php`) — so this only matters if the categories are to be maintained
by hand.

## The Virtual Labs scheme — `cli/apply_lab_scheme.php`

MCA/BCA Virtual Labs and the MCom/BCom Virtual Accounting Labs offer **two questions per section and
count only the higher one**. Moodle was adding up everything a learner attempted, so course totals ran
to /175, /210, /440 and even /1375.

| Component | Structure | Worth |
|---|---|---|
| CA 1, CA 2, CA 3 | 2 questions ×10 (best) + 2 questions ×15 (best) | 25 each |
| Practice — MCA/BCA | 5 questions ×2 + 5 questions ×3, all counted | 25 |
| Practice — Accounting | 3 sections of 2 questions (10, 10, 5), best of each | 25 |
| **Final score** | the four components summed, then floored | **100** |

A learner scoring **35 to just under 40 is topped up to exactly 40**, the pass mark — a top-up, not a
flat bonus, so nobody already passing is moved and nobody is lifted past the pass mark by it.

The gradebook does all of it natively:

```
Course total  (calculated: the moderation floor)   /100
├── Continuous Assessment - 1   Natural            /25
│   ├── Section 1   Highest grade  [2 questions]   /10
│   └── Section 2   Highest grade  [2 questions]   /15
├── Continuous Assessment - 2 … 3                  /25 each
└── Practice Questions (Graded)  Natural           /25
```

"Highest grade" (`GRADE_AGGREGATE_MAX`) *is* best-of-two, and with **aggregate only non-empty grades
off** an unattempted question counts zero — so `max(0, x)` is still `x` and a learner who answered only
one of a pair still scores it, which is the rule as written. The sub-category maxima are set by hand,
which is safe because `auto_update_max()` returns early for every non-Natural aggregation.

The moderation floor is a **calculated course total**, which Moodle's formula engine can express
(`if`, `and`, `sum` and the comparison operators all exist in `lib/evalmath/evalmath.class.php`):

```
=if(and(sum([[vlca1]],[[vlca2]],[[vlca3]],[[vlpr]])>=35,
        sum([[vlca1]],[[vlca2]],[[vlca3]],[[vlpr]])<40),
   40, sum([[vlca1]],[[vlca2]],[[vlca3]],[[vlpr]]))
```

The script sets those ID numbers on the four component categories. A calculated course total is no
longer auto-summed, which is the intent.

### How sections are identified

By **course section**, never by activity name — every lab asks different questions, so there is nothing
in a question's name to key on. Section names are themselves inconsistent (`Continuous Assessment - 1`,
`Continuous Assessments -1`, `CONTINUOUS ASSESSMENT 1`, `Continous Assessment - 03`,
`DAA Lab Continuous Assessments -1`), so they are normalised the same way CA quiz names are above.

A name only **routes** a section; its **shape** — how many questions, out of what — is what decides,
and both have to agree. Hands-on practice sections are recognised first, because they are also called
"practice" but carry no marks; they and anything else outside the scheme (live sessions, the Accounting
labs' demo question) get `weightoverride` with a weight of 0, so their marks stay visible while
contributing nothing.

```
php local/cagrades/cli/apply_lab_scheme.php --labs                      # dry run, all 49 lab courses
php local/cagrades/cli/apply_lab_scheme.php --courses=1233 --execute --backup=/path/labs.json
php local/cagrades/cli/apply_lab_scheme.php --rollback=/path/labs.json --execute
```

Same guarantees as the CA tool: dry run by default, journals before mutating, appends to an existing
backup so batched runs stay rollbackable, and after each course it re-derives every learner's total
from the scheme and **aborts the run** on the first disagreement.

**Only one run at a time.** `--execute` takes a cluster-wide lock through Moodle's lock API and refuses
to start if another run holds it. This is not decoration: the journal is read once at startup, held in
memory and rewritten whole, so two overlapping runs each discard the other's entries. That happened on
2026-07-31 — five courses were applied correctly and verified, but lost their rollback record, and the
entries had to be reconstructed from `grade_items_history`. Moodle's lock API rather than `flock()`
because these pods share moodledata over NFS, where file locking is not dependable.

A caution when running it over a long list: a `kubectl exec` that dies takes the CLI with it, and a
course interrupted between "tree built" and "learner totals verified" is left applied but unproven. Its
journal entry is the tell — `coursemaxafter` will be `null`. `--refresh-totals` on that course
re-verifies it without rebuilding anything.

Courses whose sections do not match are refused with the reason, not guessed at — at the time of
writing 45 of the 49 are clean and 4 are refused: three whose graded practice is worth 20 rather than
25 (so the course cannot reach 100), and one that also has only a single 10-mark question per CA.

## Hiding previous-semester CA marks — `cli/hide_past_ca.php`

Learners must see only their **current** semester's CA marks. Current means the
highest semester number the learner is enrolled in: nothing else is available.
Not one of the 1,066,896 `user_enrolments` rows carries a `timeend`, only 15 of
1,902 courses carry an `enddate`, and no profile field holds a semester.

The semester number comes from the course's **direct category name** through
`\theme_baz\local\semester::semester_of()` — the site's single source of
semester truth, already mirrored in `theme/baz/amd/src/mycourses.js`. It is
called rather than copied on purpose: a fourth copy of that regex deciding *what
learners may see* is how you end up with the dashboard saying "Semester 4" while
the gradebook hides Semester 4's marks. The ~131 semester categories are
resolved in PHP and inlined into the query as a `CASE`, so SQL and PHP cannot
disagree about whether `Semester - 02 - BBA` means 2 or 22.

### Why the flag is per learner

Visibility is set on `{grade_grades}.hidden`, not `{grade_items}.hidden`.
`grade_items` is course-wide and **cannot express the rule**: in one Semester-3
course, a learner whose current semester *is* 3 must still see the CA, while a
learner repeating it from an earlier batch, now in Semester 4, must not. Same
item, two answers.

Two further reasons the item-level flag is wrong here:

- It would blank the CA category total and the course total for the whole cohort.
- It changes **completion**. `completion_info::internal_get_grade_state()` reads
  `$item->hidden`, not `$grade->hidden`, so any CA with a `gradepass` would flip
  from `COMPLETION_COMPLETE_FAIL` to `COMPLETION_COMPLETE_FAIL_HIDDEN` for
  everyone. The per-learner flag leaves completion untouched.

`grade_grades.hidden` is also what core honours everywhere — the web reports,
`gradereport_user_get_grade_items` (so the mobile app), and exports. A
theme-side filter would not be: `theme/baz/layout/tmpl-report.php` deliberately
degrades to the core report on any exception, so a presentation-only control is
one PHP warning away from leaking.

### What is covered

Both the numbered CA items **and** the `Continuous Assessment` category total
above them — `ca_items::where_with_category_sql()`. The total *is* the CA mark
out of 30; hiding only the leaves would leave it on screen. On this site that is
2,069 leaves plus 597 category totals.

### Two rules that bound every write

- **One-directional.** Only ever `hidden = 0 -> 1`, never the reverse. A mark the
  teaching team has deliberately hidden stays hidden, and nothing here can
  reveal a mark that is not already visible.
- **The current semester is never touched.** Whatever is visible there stays
  visible and whatever is hidden stays hidden, so the per-item release the
  teaching team drives keeps working exactly as it does today. The CLI asserts
  this after every run and fails loudly rather than reporting success.

Write exactly `1`, never a timestamp: `grade_report_user_showhiddenitems` is `1`
("hidden-until only") on this site, under which a timestamped `hidden` makes
`grade_grade::is_hiddenuntil()` true and **the row renders**, with its reveal
date on it.

### The backlog rule

A row is left alone while it is **ungraded** (there is no mark to hide) and while
**any CA quiz in that course is still open** (the learner can still sit it).

The "graded" test is per row and the "still open" test is per course,
deliberately. Under best-two-of-three a learner routinely skips one CA, so 648
of the 706 courses holding CA items have an ungraded CA row somewhere — treating
"any ungraded CA" as "this course is still in progress" would exempt almost
everything and make the feature a no-op. An open quiz, by contrast, genuinely is
a course-wide fact, and it must exempt the category total as well as the leaves
or the total would vanish while its components were still on screen.

### Why direct SQL, not `grade_grade::set_hidden()`

`set_hidden()` is one wide `update_record` plus a `grade_grades_history` insert
plus `completion_info::inform_grade_changed()` per row. At ~380,000 rows that is
millions of queries and ~380,000 completion re-evaluations which
`internal_get_grade_state()` provably ignores. The column carries no derived
state — nothing aggregates from it, no cache is keyed on `grade_grades` — so a
batched `UPDATE ... WHERE hidden = 0` is equivalent for a plain hide, and the
`hidden = 0` in the WHERE keeps it idempotent and the tally truthful.
`timemodified` is deliberately not touched: it is "date graded" in the learner's
report, and bumping it would relabel every CA as graded today.

### Drift, and why this is a scheduled task

The gradebook's own show/hide control calls
`grade_item::set_hidden($hidden, true)` — `grade/edit/tree/action.php:68` and
`:83` — and that **cascade rewrites the hidden flag on every `grade_grade` row of
the item**, wiping the per-learner values. So a one-off sweep decays. The daily
`local_cagrades\task\hide_past_ca` repairs it, and because the rule only hides,
the repair is safe: a deliberate staff hide survives, and a staff reveal of a
previous semester's CA is undone on the next run rather than leaking.

```
# what would change, plus per-semester counts for the sample learners
php local/cagrades/cli/hide_past_ca.php

# one learner, for a spot check
php local/cagrades/cli/hide_past_ca.php --user=26913

# apply, journaling every changed row
php local/cagrades/cli/hide_past_ca.php --execute --backup=/path/pastca.json

# undo exactly what that run hid
php local/cagrades/cli/hide_past_ca.php --rollback=/path/pastca.json --execute
```

### Enabling the task

It ships **disabled** on purpose, so a deploy cannot apply the first sweep by
itself. That happened once, on 2026-09-05: the task shipped enabled, the deploy
registered it, and cron hid all 382,674 rows overnight before anyone had signed
off the count. The result was correct; the sequencing was not.

The order on any new site is: dry run, read the count, apply with `--execute
--backup=<file>`, then enable the task to keep it true.

Enable it through the API, not with a direct UPDATE:

```php
$task = \core\task\manager::get_scheduled_task('\local_cagrades\task\hide_past_ca');
$task->set_disabled(false);
$task->set_customised(true);
\core\task\manager::configure_scheduled_task($task);
```

Only that marks the task **customised**, and
`manager::reset_scheduled_tasks_for_component()` re-reads `db/tasks.php` on
every upgrade and resets any task that is not
(`lib/classes/task/manager.php:153`). Writing `task_scheduled.disabled` by hand
works until the next deploy silently reverts it.

### What this cannot close

`{grade_grades}.hidden` governs the gradebook. It does **not** govern
`mod/quiz/view.php`, which shows a learner their best grade based purely on the
quiz's review options, nor `mod_quiz_get_user_best_grade`. All 2,039 CA quizzes
have "marks after close" disabled, so a closed quiz is silent — but **4 CA
quizzes have no close date** and therefore never enter the after-close state.
Those four sit in `OBHRM`, which is in the site root category rather than a
semester, so the semester rule never reaches them anyway; give them a close date
if they are ever moved into a semester.

Related: `grade_report_user_showtotalsifcontainhidden` is `0` on this site, so a
past-semester course's CA category total and course total go blank once their
components are hidden. That was accepted deliberately — the alternative is a
site-wide display change that would also alter the current semester, which was
ruled out.

## Install

New plugin, so it needs a Moodle upgrade run after the code is deployed:

```
php admin/cli/upgrade.php --non-interactive
php admin/cli/purge_caches.php
```

Then the source appears as **Continuous assessment grades** in the New report dialog. Nothing else
to configure; access is governed by the ordinary report builder capabilities
(`moodle/reportbuilder:edit`, `moodle/reportbuilder:editall`).
