---
doc: scope
status: approved
---

# GenAI Ratel CMS

An AI social media content manager that plans a business's next round of posts from its own website and from how its previous posts performed — and shows its evidence.

## The Unique Kernel
**The engagement-based learning loop, made visible.** The second batch of content ideas visibly changes because of the first round's observed performance, and the screen shows *why*: which knowledge record influenced each idea, the supporting post IDs, and the metrics behind it. Generic AI tools write posts; this one explains what it learned and what it changed.

Equally important to the learner: it never overclaims. No insight from a single post, correlation is not presented as causation, and synthetic evidence is always labelled as such.

## Who It's For
The marketing manager of **Aquarium Park Dhaka** (https://aquariumpark.com.bd/). Every week they plan campaigns to attract visitors and ticket sales, write posts about the aquarium and the visitor experience, prepare images and videos, schedule, and check engagement. Today that means switching between separate tools for writing, design, video, scheduling and analytics, and past performance rarely shapes what gets made next.

Broader audience later: SMBs, entrepreneurs, marketers and social media managers.

## The Core Loop
1. Point it at the business website → it builds an editable business profile grounded in that site.
2. It proposes a small batch of platform-specific content ideas (round one).
3. Generate text + image for an idea on one platform, preview it, approve it, schedule it.
4. Load past post performance → it produces evidence-backed knowledge records.
5. Ask for the next batch → ideas change, each with "why this was suggested" and its evidence.

They come back because each round of results makes the next round of ideas better informed.

## Inspiration & Identity
Professional and trustworthy — a tool a marketing manager would show their boss. Evidence and provenance are first-class in the UI, not footnotes. Visual direction to be settled in `3-prd`.

## Why This Matters to the Learner
"I want to use this hackathon to improve my plan-first, specification-driven AI coding workflow while building a functional, well-tested project." The learner's earlier Laravel app (reference only, not reused) lacked exactly these pieces: scheduled publishing, a separate approval step, and business knowledge feeding generation.

## What "Working" Looks Like
In a demo under three minutes, with no social-account credentials:
1. Enter the Aquarium Park website → an editable, sourced business profile appears.
2. Round-one ideas are generated; one becomes platform-specific text + image, previewed, approved and scheduled.
3. The synthetic historical dataset is loaded; knowledge records appear, each linked to its supporting posts, sample size, metric, calculation and confidence — all marked **Synthetic Demo Data**.
4. **The "oh, that's cool" beat:** round two is generated. Each idea shows the knowledge record that shaped it, the supporting synthetic post IDs and metrics, and a **Simulated Insight** label. A comparison panel shows how round two differs from round one in format, topic, campaign goal and call to action.

This demonstrates the feedback loop. It does not prove engagement improved, and the README says so.

## The POC Boundary
- **Business profile** from the real public website, editable, with sources. Inaccessible sites fail clearly and fall back to manual input.
- **Round-one ideas**: a small batch of platform-specific text/image ideas from the profile.
- **Content generation**: text + image for at least one platform, with preview.
- **Approval and scheduling**: explicit administrator approval, a future schedule time (`Asia/Dhaka`), and a server-side scheduler processing due items. External publishing is **disabled** — no verified account access exists — and the UI says so rather than faking success.
- **Performance ingestion** from `aquariumpark_synthetic_demo/aquariumpark_synthetic_posts.json` (30 fictional records, 2026-09-01 to 2026-10-13; the canonical source), read-only, validated, provenance kept on every record (`data_source`, `is_real_performance_data`, dataset version). Designed so authorized CSV exports or APIs can be added later as a separate source.
- **Knowledge records**: observation, recommendation, supporting post IDs, sample size, metrics and calculation method, confidence, `evidence_type: synthetic`, `source_dataset: aquariumpark_synthetic_demo`. No record from a single post.
- **Round-two ideas** using knowledge records as explicit AI-prompt inputs, with per-idea traceability and a round-one vs round-two comparison panel.
- Visible **Synthetic Demo Data** / **Simulated Insight** labels wherever synthetic metrics or derived insights appear.

## Later
- Real publishing through one verified integration once account access and permissions exist (Facebook Page first).
- Authorized real analytics: CSV export import, then platform API metrics — kept separate from synthetic data.
- Video generation (scripts, scene plans, rendered video).
- More platforms, multiple accounts, separate reviewer role, advanced analytics and experiments.
- Social-profile analysis beyond the website, competitor observations, profile version comparison.

## Explicitly Cut
- **Live publishing to Aquarium Park's accounts** — no confirmed admin access or permission; must stay disabled until verified.
- **Waiting for real engagement on new posts** — the hackathon window is too short for a meaningful sample.
- **Rebuilding the earlier Laravel app's feature set** (packages, costing, video editor, six networks) — not needed to prove the loop, and the project must be new.
- **Model fine-tuning / "self-training"** — the loop is transparent retrieval and prompting, not training.
- **Video asset generation** in the PoC — ideas may *propose* video formats, but only text + image are generated.

## Open Questions for Later Stages
- Resolved: future-dated records fixed by the learner in the JSON (now ends 2026-10-13). The CSV still carries the old dates (to 2026-10-29) and Excel formatting (`9/1/2026`, `FALSE`); metrics match the JSON. The JSON is canonical; the CSV should be re-exported from it or left unused before shipping.
- What a due scheduled post becomes when publishing is disabled (e.g., "Due — not published: no verified account") — define in `3-prd`.
- Small groups (e.g., only 2 Text posts) should yield "insufficient evidence", not an insight — threshold to set in `3-prd`.
