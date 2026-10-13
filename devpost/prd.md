---
doc: prd
status: approved
---

# GenAI Ratel CMS — Product Requirements

A guided, five-step content intelligence workspace for the marketing manager of Aquarium Park Dhaka: profile the business, plan and generate content, approve and schedule it, learn from performance evidence, and generate a better-informed second batch, with every insight traceable to its evidence.
Source: `scope.md > The Unique Kernel`, `scope.md > Who It's For`.

## The Core Journey
Source: `scope.md > The Core Loop`, `scope.md > What "Working" Looks Like`.

1. The administrator signs in (or opens a clearly labelled demo workspace) and lands on the **Dashboard**, which shows overall progress and one prominent **Continue** action for the next incomplete step.
2. **Step 1 — Business Profile.** They enter the Aquarium Park website (plus any social URLs). The app builds a profile from the public site, separating verified public facts from AI assumptions. They edit and **Confirm** it.
3. **Step 2 — Round-One Ideas.** The app generates a small batch of ideas from the confirmed profile, grouped by platform, campaign goal and format (text, image, video/Reel). They select ideas to generate.
4. **Step 3 — Generate, Review and Approve.** Each selected idea becomes a content item: caption, hashtags and CTA, plus an image (image ideas) or a video production brief (video ideas). They edit, regenerate, submit for approval, then approve, request changes or reject, and schedule approved items.
5. **Step 4 — Load and Analyse Performance.** They load the synthetic dataset. The app shows metrics and comparisons by format and campaign goal, and produces insights only where evidence thresholds are met, each with sample size, confidence and source.
6. **Step 5 — Knowledge and Round Two.** Insights are saved as knowledge records. They generate round two; each new idea shows which knowledge record influenced it and why. A comparison panel shows how round two differs from round one.
7. **Success:** round two visibly differs from round one, every difference traces back to labelled synthetic evidence, and nothing claims real publication or proven improvement.

## Screens and Layout

Compact left sidebar with five destinations:

| Destination | Purpose |
|---|---|
| **Dashboard** | Status at a glance and the next action |
| **Business Profile** | Step 1 (also reachable from the workflow) |
| **Content Workflow** | The five-step guided path with a persistent progress indicator |
| **Performance & Insights** | Step 4 analysis |
| **Knowledge Base** | Knowledge records and their evidence |

- **Content Workflow** shows a persistent five-step progress indicator (current, completed and outstanding steps). Completed steps can be revisited without losing work.
- **Dashboard** contains: business profile status; current workflow step and progress; round-one generation and approval status; performance data source with a synthetic/real indicator; knowledge insights from round one; round-two status; and a prominent **Continue: [next step]** action.
- No other screens. The broader brief's 14-item navigation is not built.

## Look and Feel
Source: learner's design answers.

- **Character:** professional, trustworthy, modern SaaS with a *subtle* aquatic identity. Not childish and not a generic AI landing page. It should feel like an editorial review tool.
- **Colour:** deep navy or dark teal for the sidebar, headings and primary navigation; teal or ocean blue for primary actions and active workflow states; white and very light cool grey for the workspace and cards; soft aqua for secondary highlights; neutral grey for supporting text. Green, amber and red are reserved for meaningful statuses (approved, pending, rejected or failed). Accessible contrast.
- **Typography and layout:** clean, highly readable sans-serif with clear hierarchy; generous whitespace; consistent cards, restrained borders, subtle shadows; compact, labelled charts; easily scanned tables.
- **Evidence-first:** persistent source labels. Five visually distinct provenance types: *Verified public information*, *AI-generated assumption*, *Synthetic demo metric*, *Verified real performance data* (future), and *AI-generated insight* (tagged with its source). Every insight can be expanded to show its supporting records and reasoning.
- **Avoid:** purple gradients and glowing effects; excessive animation; decorative aquarium imagery competing with the work; crowded navigation; charts without labels, sample sizes or source; unexplained AI recommendations; fake publishing, fake live integrations or misleading real-time indicators; complex configuration screens.

## Features and Behavior

### Access
- A single administrator. If authentication fits the build, a simple login protects the workspace. Otherwise the workspace opens directly with a persistent **Demo Workspace — not a secure deployment** label.
- No multi-user roles, teams or invitations.
- Work persists between sessions: closing and reopening the app keeps the profile, ideas, content, statuses, knowledge records and round two.

### Step 1 — Business Profile
Source: `scope.md > The POC Boundary` (business profile).

- Inputs: website URL (required, validated) and optional social URLs (Facebook, Instagram, LinkedIn, X, YouTube, TikTok).
- The app reads the public website and produces: business overview, audience assumptions, attractions and offerings, content opportunities, suggested posting times, and recommended platforms.
- Every field is labelled **Verified public information** (with source URL and retrieval time) or **AI-generated assumption**. Social URLs are recorded, but their content is not analysed in the PoC; the profile says so.
- All fields are editable. **Confirm Profile** is required before Step 2. Editing after confirmation requires re-confirming.
- Acceptance:
  - [ ] Entering `https://aquariumpark.com.bd/` produces a profile with at least one field labelled verified, each with a source URL.
  - [ ] Assumptions are visibly distinct from verified facts.
  - [ ] An invalid URL is rejected with a clear message before any analysis.
  - [ ] An unreachable site shows a clear error and offers manual entry of the profile fields.
  - [ ] Step 2 is locked until the profile is confirmed.

### Step 2 — Round-One Content Ideas
- Generates a small batch (around 6) from the confirmed profile only, with no performance knowledge.
- Ideas are grouped or filterable by platform, campaign goal and format (Text, Image, Video/Reel).
- Each idea shows: topic or title, intended audience, objective or campaign goal, platform, format, and proposed CTA. Video/Reel ideas also show: proposed duration, aspect ratio (e.g., 9:16), hook and key message.
- The manager selects one or more ideas to generate.
- Acceptance:
  - [ ] Ideas reference specifics from the confirmed profile (e.g., real attractions), not generic aquarium filler.
  - [ ] At least one Image and one Video/Reel idea appear in the batch.
  - [ ] Selected ideas proceed to Step 3; unselected ideas remain visible.

### Step 3 — Generate, Review and Approve
Source: `scope.md > The POC Boundary` (content generation; approval and scheduling).

**Generation**
- **Text and image ideas:** platform-specific caption, hashtags and CTA, plus a generated image at the platform's aspect ratio, with image alt text.
- **Video/Reel ideas:** a **video production brief**: script or voice-over, scene-by-scene shot list, suggested visuals and on-screen text, estimated duration, caption, hashtags and CTA, plus a cover image if image generation is available. Labelled **Video Not Generated — Planning Preview Only**. No video player.
- Cards show caption, visual preview, platform, format, proposed date and time, and status.
- Actions: edit text, regenerate (optionally with instructions), submit for approval.

**Content states**

| State | Meaning |
|---|---|
| Draft | Being created or edited |
| Pending Approval | Submitted for review |
| Approved | Approved, not yet scheduled or published |
| Rejected | Declined, with a required reason |
| Scheduled | Approved and assigned a future date/time (Asia/Dhaka) |
| Published | Publication confirmed. The record shows how: **Simulated (demo)**, **Manually confirmed**, or **Verified by platform** (not available in the PoC) |
| Publishing Failed | An attempted publication failed, with the error or next action |

**Transitions**
- Draft → Pending Approval (submit)
- Pending Approval → Approved (approve)
- Pending Approval → Draft (**Request Changes**: feedback required and shown on the item)
- Pending Approval → Rejected (**Reject**: reason required)
- Rejected → Draft (explicit **Reopen**)
- Approved → Scheduled (pick a future date and time)
- Scheduled → Scheduled (reschedule) or Approved (unschedule)
- Scheduled → Published (only via **Mark as Manually Published**, confirmed by the user, or **Simulate Publishing** in demo mode)
- Editing an Approved or Scheduled item returns it to Draft.

**Scheduling with publishing disabled**
- Date and time pickers in Asia/Dhaka; past times are rejected.
- When the time arrives, the item is **not** marked Published. It displays **Ready to Publish — Manual Action Required** (a display state of Scheduled), with the scheduled time, the platform, and "Automatic publishing is disabled: no verified account connection."
- **Simulate Publishing** records a result labelled *Simulated — not sent to any platform*.
- **Mark as Manually Published** requires an explicit confirmation that the post really went out.

- Acceptance:
  - [ ] A video idea produces a script and shot list with the "Video Not Generated" label and no video player.
  - [ ] An image idea produces a visible image matching the platform's aspect ratio.
  - [ ] Reject without a reason is refused; Request Changes without feedback is refused.
  - [ ] Request Changes returns the item to Draft with the feedback visible.
  - [ ] Only Approved items can be scheduled; a past time is rejected.
  - [ ] When a scheduled time passes, the item shows "Ready to Publish — Manual Action Required" and is not Published.
  - [ ] Simulated publication is visibly distinct from manual confirmation everywhere it appears.
  - [ ] Each item shows its status history: who changed it, when, from which state to which, and any feedback or reason.

### Step 4 — Load and Analyse Performance
Source: `scope.md > The POC Boundary` (performance ingestion).

- **Load Synthetic Demo Dataset** reads the 30 records from `aquariumpark_synthetic_demo` without modifying the source files. The dataset is validated, and its provenance (source, `is_real_performance_data=false`, version) is kept on every record.
- A persistent banner: **Synthetic Demo Data — Not Real Aquarium Park Analytics**.
- Displays the records table, plus comparisons by content format, campaign goal and platform, using the dataset's documented metric definitions:
  - engagements = likes + comments + shares
  - engagement rate = engagements ÷ reach × 100
  - CTR = link clicks ÷ impressions × 100

  Each metric shows its formula. Rates with different denominators are never compared as equivalent. A missing metric is shown as "N/A", never as 0.
- **Evidence thresholds** apply to the comparable posts behind each claim, not the whole dataset:

| Comparable posts | Result | Confidence |
|---|---|---|
| fewer than 5 | Descriptive metrics only, labelled **Insufficient Evidence**; no recommendation | Insufficient Evidence |
| 5–9 | **Preliminary Observation** with a small-sample warning | Low |
| 10 or more | **Sample-level insight**, if results are reasonably consistent | Medium (Low if variation is substantial) |
| — | Not assigned in the PoC; never from sample size alone | High |

- Every confidence label shows n, e.g., `Low Confidence · n=7`. No invented confidence percentages.
- Synthetic insights are described as *simulated sample-level observations*.
- A second import path for authorized real exports is planned as a separate source but not built.
- Acceptance:
  - [ ] Loading the dataset shows all 30 records with the synthetic banner; the source files are unchanged.
  - [ ] Text posts (n=2) and Conversion posts (n=2) show Insufficient Evidence and no recommendation.
  - [ ] Every insight shows its sample size, confidence label, formula and source.
  - [ ] Loading twice does not duplicate records.

### Step 5 — Knowledge Feedback and Round Two
Source: `scope.md > The POC Boundary` (knowledge records; round-two ideas).

- Qualifying insights become **knowledge records**, each with: observation, recommendation, supporting post IDs, sample size, metrics and calculation method, confidence label and rationale, `evidence_type: synthetic`, `source_dataset: aquariumpark_synthetic_demo`, and date of analysis.
- **Knowledge Base** lists the records; each expands to show its supporting synthetic posts.
- **Generate Round Two** uses the confirmed profile **plus** the knowledge records as explicit inputs. Each round-two idea shows:
  - **Why this was suggested**
  - the influencing knowledge record(s)
  - supporting post IDs and metrics
  - a **Simulated Insight** label

  Ideas not driven by knowledge (e.g., a continued test of a lower-performing format) are labelled as such.
- A **comparison panel** shows round one against round two by topic, format, campaign goal and CTA, with the note: "This demonstrates the feedback loop working; it does not show that real engagement improved."
- Acceptance:
  - [ ] Knowledge records exist only for insights at or above the Preliminary threshold (n ≥ 5).
  - [ ] Every round-two idea either names its influencing knowledge record(s) or is labelled as not knowledge-driven.
  - [ ] Round two differs from round one in at least one compared dimension, and the comparison panel shows the difference.
  - [ ] Opening a knowledge record shows the exact supporting post IDs.

## States and Boundaries
- **First use:** the Dashboard shows nothing completed and **Continue: Step 1 — Business Profile**. Steps 2–5 are visibly locked with the reason (e.g., "Confirm the business profile first").
- **Empty states:** before the dataset loads, Performance & Insights explains what loading does and that the data is synthetic. Knowledge Base explains that records appear after analysis.
- **Loading:** profile analysis, idea generation and content generation show progress messages. Buttons are disabled while running to prevent double submission.
- **Errors:** an unreachable website leads to manual entry; an AI generation failure shows the error with **Retry**, and existing work is kept; image generation failure keeps the text and offers retry; a dataset that is missing or invalid shows what failed. No item ever shows success for work that did not happen.
- **Persistence:** all work survives closing and reopening the app.
- **Integrity:** synthetic and (future) real data are never merged; provenance travels from records to insights, knowledge, and round-two ideas.

## Product Decisions
- Guided five-step workflow plus a compact dashboard, rather than a full 14-item admin, to keep the journey obvious.
- Synthetic dataset for round one, because there's no account access and too little time for real engagement.
- Video ideas produce production briefs, not videos, because video generation is cut but video is too important to omit from planning.
- Seven content states; Request Changes is separate from Reject, so "needs work" isn't confused with "declined".
- Publishing is disabled. Overdue scheduled items need explicit manual confirmation or a labelled simulation, to avoid fake success.
- A single administrator, with a simple login if feasible; otherwise a labelled demo workspace.
- Evidence thresholds of 5 and 10 are practical PoC rules, not statistical standards. High confidence is not assigned in the PoC.

## What We're Building
The Dashboard plus the five steps above with their acceptance criteria: website-grounded profile; round-one ideas (text, image, video); text and image generation and video briefs; the approval and scheduling workflow with seven states and history; synthetic data loading with threshold-governed insights; knowledge records; round two with traceability and a comparison panel; provenance labels throughout; persistence; single-admin access.

## Deferred From the POC
- **Real publishing:** needs verified account access and permissions for Aquarium Park.
- **Real analytics import (CSV/API):** needs authorized access. Planned as a separate source.
- **Social-profile content analysis:** URLs are stored only, because platform access is restricted.
- **Video rendering:** cut from scope.
- **Separate reviewer role / multi-user:** single admin is enough to show approval.
- **Profile version comparison and history:** not needed to prove the loop.

## Possible Later Enhancements
Facebook Page publishing as the first verified integration; real metrics alongside synthetic with separate comparisons; rendered short videos from the briefs; controlled experiments suggested when evidence is inconclusive; knowledge record review, retirement and staleness detection.

## Non-Goals
- Any claim that synthetic insights describe Aquarium Park's real audience, or that round two improves real engagement.
- Fake publishing success, live-integration indicators or real-time claims.
- Model fine-tuning or "self-training".
- Rebuilding the earlier Laravel app's modules (packages, costing, video editor, multi-network posting).

## Open Questions
- **For `4-spec`:** how "reasonably consistent" and "substantial variation" are measured to move between Medium and Low confidence. It needs one simple, documented rule.
- **For `4-spec`:** whether simple login fits the build, or the labelled demo workspace is used.
- **For `4-spec`:** which AI text and image providers are available to the learner (credentials).
- **Can wait:** exact number of ideas per round (around 6 assumed).
