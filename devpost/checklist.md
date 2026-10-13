---
doc: checklist
status: approved
---

# Build Checklist

Build mode: fast (hands-on checkpoints after slice 2 and at final review)

## Slices

- [x] **1. Log in and load the synthetic dataset**
  Becomes usable: A running Laravel app with login, the navy sidebar layout and mode badge. Performance & Insights loads the 30 synthetic posts from the canonical JSON into SQLite and shows them in a table under a persistent "Synthetic Demo Data — Not Real Aquarium Park Analytics" banner.
  Why now: Bootstraps the whole stack (Composer on this machine, SQLite, auth, layout, CSS) on the data the kernel depends on. If the dataset doesn't fit the model, we find out first.
  PRD ref: `prd.md > Access`, `prd.md > Step 4 — Load and Analyse Performance`, `prd.md > Look and Feel`
  Spec ref: `spec.md > Stack`, `spec.md > Authentication`, `spec.md > SyntheticDatasetLoader`, `spec.md > Data Model`, `spec.md > File Structure`, `spec.md > Look and Feel`
  Build: Create a Laravel 12 skeleton in a temp folder and merge it into the workspace without touching existing files. Configure SQLite; add `config/ai.php` and `config/performance.php`; admin seeder from `.env`; hand-rolled login with auth middleware; the `layouts/app` sidebar and top bar, `app.css` palette and the `provenance` badge component. Add the `performance_datasets` and `performance_records` migrations and models, plus `SyntheticDatasetLoader` (validate, transaction, upsert by post_id). Add the Performance page with the load button, records table and banner.
  Verify (mechanical): `php artisan migrate:fresh --seed` succeeds; a feature test logs in, loads the dataset, asserts 30 records and the banner text, loads again and still asserts 30, and asserts the JSON file hash is unchanged; an unauthenticated request redirects to login; `php artisan serve` responds 200 on `/login`.
  Learner check: Run `php artisan serve`, log in with the demo admin from `.env`, open Performance & Insights, click Load, and check that 30 posts appear under the synthetic banner and the look matches what you pictured.
  Commit: `Scaffold app with login and synthetic dataset loading`

- [x] **2. See evidence-graded insights and knowledge records**
  Becomes usable: Performance & Insights shows comparisons by format, campaign goal and platform+format with n, excluded count, mean ER, CV, CTR, formulas and confidence chips (e.g., `Low Confidence · n=7 · CV=0.61`). Groups under 5 posts show Insufficient Evidence. Saving creates knowledge records, browsable in the Knowledge Base with their supporting post IDs.
  Why now: This is the kernel's evidence half; everything in round two depends on it. It's pure arithmetic, so it can be proven with unit tests before any AI exists.
  PRD ref: `prd.md > Step 4 — Load and Analyse Performance`, `prd.md > Step 5 — Knowledge Feedback and Round Two`
  Spec ref: `spec.md > PerformanceAnalyzer`, `spec.md > KnowledgeBuilder`, `spec.md > Data Model`, `spec.md > Look and Feel`
  Build: `PerformanceAnalyzer` (groups, ER, population SD, CV with mean-zero guard, CTR, confidence ladder, comparison against the rest of the dimension). `confidence` Blade component and CSS bar charts. `knowledge_records` migration and model; `KnowledgeBuilder` (stable keys, upsert, n ≥ 5 only). Knowledge Base list and detail pages.
  Verify (mechanical): Unit tests against the real dataset: Text (n=2) and Conversion (n=2) are Insufficient with no recommendation; Reel (n=11) gets a confidence label computed from its actual CV; a hand-built group with mean ER of 0 never gets Medium; High is never assigned; excluded records are counted. Feature test: building knowledge twice yields the same record count, and every record has supporting post IDs, `evidence_type=synthetic` and the source dataset.
  Learner check: Open Performance & Insights and check that small groups say Insufficient Evidence while larger ones show n and CV. Generate knowledge, open a record in the Knowledge Base, and check that its supporting post IDs match the posts in the table.
  Commit: `Add evidence-graded performance analysis and knowledge records`

- [x] **3. Build and confirm the business profile from the website**
  Becomes usable: Business Profile takes a URL, reads aquariumpark.com.bd, and shows verified facts (name, attractions, email, social links, each with source URL and time) beside fixture-labelled AI assumptions. Everything is editable, Confirm locks it in, and an unreachable or invalid URL falls back to manual entry.
  Why now: Round two needs a confirmed profile as its other input, and this is the one live external dependency on the demo path, so we test it early.
  PRD ref: `prd.md > Step 1 — Business Profile`
  Spec ref: `spec.md > WebsiteReader`, `spec.md > AI Provider Layer`, `spec.md > Data Model`, `spec.md > Important Failure Modes`
  Build: `WebsiteReader` (URL validation, private-host guard applied to every redirect hop with redirects followed manually, fetch home plus up to 8 same-domain pages, DOMXPath extraction). `AiProvider` interface, `FixtureProvider::profile()` and `resources/fixtures/profile.json`. `business_profiles` migration and model; profile form, edit and confirm (editing reverts it to draft); manual-entry fallback.
  Verify (mechanical): Feature tests with `Http::fake()`: a sample page yields verified facts with source URLs; an invalid URL and a private host (`http://127.0.0.1`) are rejected before any request; a redirect to a private address is refused without following it; a 500 or timeout shows the manual form; confirming sets status, and editing reverts it to draft. Plus one real run against https://aquariumpark.com.bd/ via the app, checking the extracted attractions.
  Learner check: Enter the Aquarium Park URL and check that verified facts carry source links and look visibly different from AI assumptions. Edit a field, confirm, then try `not-a-url` and an unreachable site to see the error and manual form.
  Commit: `Add website-grounded business profile with provenance`

- [x] **4. Generate round one and an evidence-driven round two, with comparison**
  Becomes usable: From the confirmed profile, the app generates about 6 round-one ideas (Text, Image, Video/Reel) grouped by platform, goal and format, which can be selected. Generate Round Two then produces ideas that each show "Why this was suggested", their influencing knowledge record(s), post IDs and a Simulated Insight label (or "not knowledge-driven"). A comparison panel diffs format, goal, platform and CTA between the rounds, with the disclaimer.
  Why now: This completes the kernel, so the full "oh, that's cool" beat is demoable before any approval plumbing.
  PRD ref: `prd.md > Step 2 — Round-One Content Ideas`, `prd.md > Step 5 — Knowledge Feedback and Round Two`
  Spec ref: `spec.md > AI Provider Layer`, `spec.md > RoundComparison`, `spec.md > Data Model`
  Build: `content_rounds` and `content_ideas` migrations and models. `FixtureProvider::ideas()` with `ideas_round1.json` and `ideas_round2.json` (each idea tagged with `insight_keys`; selection driven by the knowledge records that exist; untagged exploratory ideas). Ideas page with filters and selection; round-two view with traceability; `RoundComparison` and the comparison panel.
  Verify (mechanical): Feature tests: round 1 is refused without a confirmed profile; round 1 includes Image and Video/Reel ideas; round 2 is refused without knowledge records. Every round-two idea has `influenced_by` IDs that exist, or `knowledge_driven=false`. Removing one knowledge record changes the round-two selection, proving genuine influence. The comparison reports at least one differing dimension.
  Learner check: Generate round one and select two ideas. Then generate round two and read one "Why this was suggested". Does it name a knowledge record and post IDs that match the Knowledge Base? Does the comparison panel tell the story clearly?
  Commit: `Add round-one ideas and knowledge-driven round two with comparison`

- [x] **5. Generate content and run it through approval**
  Becomes usable: Selected ideas become content items. Image ideas get a caption, hashtags, CTA, alt text and a labelled fixture image; video ideas get a production brief (script, shot list, on-screen text, duration) marked "Video Not Generated — Planning Preview Only". Items move Draft → Pending Approval → Approved, with Request Changes (feedback required), Reject (reason required) and Reopen, and each item shows its status history.
  Why now: Turns ideas into reviewable content and adds the human-approval safety the PRD requires. It builds on the selected ideas from slice 4.
  PRD ref: `prd.md > Step 3 — Generate, Review and Approve`
  Spec ref: `spec.md > Content Workflow (state machine)`, `spec.md > AI Provider Layer`, `spec.md > Data Model`
  Build: `content_items` and `status_histories` migrations and models. `FixtureProvider::content()` with `content.json` and bundled, labelled images in `public/demo-fixtures/images/`. `ContentWorkflow` with the transition table, transactions and history. Content cards, edit form, review actions and the `status-pill` component.
  Verify (mechanical): Unit tests for `ContentWorkflow`: allowed transitions pass; disallowed ones (e.g., Draft → Approved, Rejected → Scheduled) throw; reject without a reason and request changes without feedback are refused; editing an Approved item returns it to Draft; every transition writes history. Feature test: a video idea produces a brief and no `<video>` element.
  Learner check: Generate content for an image idea and a video idea. Submit one, request changes with feedback, and check the feedback shows on the Draft. Submit again and approve. Try rejecting without a reason, then open the history.
  Commit: `Add content generation and approval workflow`

- [x] **6. Schedule approved content without fake publishing**
  Becomes usable: Approved items can be scheduled with date and time pickers (Asia/Dhaka, stored as UTC; past times rejected), rescheduled or unscheduled. Once the time passes, the item shows "Ready to Publish — Manual Action Required" with the explanation. Simulate Publishing records a result labelled "Simulated — not sent to any platform", and Mark as Manually Published requires confirmation. `php artisan schedule:work` records the due-time history note.
  Why now: It completes the approval-to-publication path, and depends on slice 5's states.
  PRD ref: `prd.md > Step 3 — Generate, Review and Approve` (scheduling with publishing disabled)
  Spec ref: `spec.md > Content Workflow (state machine)`, `spec.md > Due Check (scheduler)`
  Build: Schedule, unschedule, simulate and manual-publish actions in `ContentWorkflow` and the UI; timezone conversion; computed Ready-to-Publish display; the `CheckDueContent` command registered every minute in `routes/console.php`.
  Verify (mechanical): Tests: scheduling a non-Approved item or a past time is refused; Dhaka input is stored as the correct UTC value; with time travelled past `scheduled_at`, the item renders Ready to Publish and its status is still `scheduled`; `content:check-due` writes one history note and is idempotent when run twice; manual publish without confirmation is refused; a simulated publish stores `publish_method=simulated`. Run `php artisan content:check-due` manually once.
  Learner check: Approve an item, schedule it two minutes ahead, run `php artisan schedule:work`, and watch it become Ready to Publish without turning Published. Then Simulate Publishing and check that the label says it wasn't sent anywhere.
  Commit: `Add scheduling with manual and simulated publication`

- [x] **7. Dashboard and guided workflow progress**
  Becomes usable: The Dashboard shows profile status, current step and progress, round-one and approval status, data source (synthetic), knowledge insights, round-two status and a prominent "Continue: [next step]". The Content Workflow shows a persistent five-step indicator; locked steps explain why, and completed steps can be revisited. Empty states are in place.
  Why now: It ties the finished steps into the guided journey the PRD describes, which needs every step to exist first.
  PRD ref: `prd.md > Screens and Layout`, `prd.md > States and Boundaries`, `prd.md > The Core Journey`
  Spec ref: `spec.md > Dashboard and Workflow Progress`, `spec.md > Look and Feel`
  Build: `WorkflowProgress` service; the `step-indicator` component on every step page; Dashboard view; step locks with reasons; empty states for Performance, Knowledge and Ideas.
  Verify (mechanical): Feature tests: a fresh database shows "Continue: Step 1"; each completed step advances the next action; Step 2 is locked with a reason before the profile is confirmed. Full core-journey test in fixtures mode, from login to round-two comparison, runs green.
  Learner check: Run `php artisan migrate:fresh --seed`, log in, and follow only the Continue buttons from Step 1 to the round-two comparison. Did you ever wonder what to do next?
  Commit: `Add dashboard and guided workflow progress`

- [ ] **8. Optional Live AI mode with Gemini**
  Becomes usable: With `AI_MODE=live` and a `GEMINI_API_KEY`, profile assumptions, ideas (round two using the knowledge records in the prompt), captions and images come from Gemini, labelled Live AI with the model name. Failures show the cause and a Switch to Demo Fixtures action, never a silent fallback.
  Why now: Last, as the learner prioritised. The loop already works without it, and its model and image access is the least verified part of the spec.
  PRD ref: `prd.md > Step 1 — Business Profile`, `prd.md > Step 2 — Round-One Content Ideas`, `prd.md > Step 3 — Generate, Review and Approve`, `prd.md > Step 5 — Knowledge Feedback and Round Two`
  Spec ref: `spec.md > AI Provider Layer`, `spec.md > Gemini API (optional, Live AI mode only)`, `spec.md > Important Failure Modes`
  Build: Start with one real test call to confirm the model names and image access. Then `GeminiProvider` (structured output with response schemas, output validated against the fixture shapes, error mapping), image saving to storage, the mode switch action, and the live/fixture provenance badges.
  Verify (mechanical): Tests with `Http::fake()`: a valid JSON response is saved as `live_ai`; invalid JSON, 429 and timeout each produce the mapped error, with no fixture output saved; the round-two prompt contains the knowledge record keys. If the learner provides a key, one real profile and idea generation works in the app.
  Learner check: If you have a key, set `AI_MODE=live`, generate round two, and compare it with the fixture version. Then break the key and confirm the error offers Switch to Demo Fixtures.
  Commit: `Add optional Gemini live AI provider`

## Hands-on Checkpoints

- [x] Early usable behavior explored — after slice 2 (evidence ladder and knowledge records visible), when feedback can still reshape round two
  Feedback: login failed after `.env` credentials changed post-seeding (fixed by re-running `AdminSeeder`; README will document it); asked why Business Profile and Content Workflow were locked (not built yet, by plan). No change requested to the evidence analysis; learner asked to continue.
- [ ] Final kick-the-tires exploration and feedback completed

## Final Review

- [ ] Final review complete — feedback resolved and learner confirms ready to ship

## Code Tour and App Map

- [ ] Learning activity complete — guided route, focused alternative, prior practice connected, or brief recap
- [ ] Optional edit and transfer reflection addressed — offered/declined/already covered/not applicable as appropriate
- [ ] `devpost/app-map.html` generated from finished code, checked, and shown, including a project-grounded practice to reuse

Activity and evidence:
Route and stops:
Edit outcome:
Reflection:
Activity mode:

## Revisions

- Slice 3: the private-host guard now also applies to every redirect hop (redirects followed manually, max 3), as the learner requested at plan approval. `spec.md > WebsiteReader` updated to match.
- Slice 1: Composer 2.2.25 created the Laravel 12 project without `self-update`; the missing `zip` extension was loaded per command (`php -d extension=zip composer.phar …`) instead of editing `php.ini`. Port 8000 is used by another local process on this machine, so local checks use `php artisan serve --port=8010`. The dataset CSV stays untracked until it matches the JSON, and the dataset README's date range ("through 2026-10-29") needs the learner's correction before shipping.
- Slice 3: the real run against aquariumpark.com.bd (9 pages, 0 skipped) showed that video links (`youtube.com/watch`) were being taken as social profiles and that list items found on subpages had only the homepage as their source. Fixed: post/video links are excluded, and every list item stores its own source page. The automated end-to-end run through the logged-in app was blocked by auto-review (it would read the admin password), so the in-app real run is part of the learner's next hands-on check.
- Slice 5: regenerating round two deletes its ideas, which would also delete content made from them. Round two now refuses to regenerate once any of its ideas has content (a guard on existing behavior, not new scope).
- Slice 6: a test exposed that a workflow action on an out-of-date copy of an item could skip a change (rescheduling didn't clear the due marker). Every transition now reloads the item inside its transaction before checking the rule. Simulate Publishing and Mark as Manually Published are offered only for Scheduled items, matching the PRD transition list.
- Slice 7: a step shows as locked only when the app actually refuses its action. On first use Steps 2, 3 and 5 are locked with reasons; Step 4 (load synthetic data) has no prerequisite, so it shows as "To do" rather than locked (the PRD's first-use note expected Steps 2–5 all locked). Step 3 counts as complete once any item is Approved, Scheduled or Published.

