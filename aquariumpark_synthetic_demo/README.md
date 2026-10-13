# Aquarium Park Synthetic Social Performance Dataset

**Status: SYNTHETIC DEMO DATA — NOT REAL AQUARIUM PARK ANALYTICS**

Version: 1.0.0  
Seed/versioning: fixed hand-authored records; identical files are reproducible.  
Intended use: demonstrate the GenAI Ratel CMS content-performance feedback loop in a hackathon PoC.

## What is included
- 30 fictional post records dated 2026-09-01 through 2026-10-29
- Facebook and Instagram examples
- Image, Reel, Carousel, and Text formats
- Fictional impressions, reach, likes, comments, shares, link clicks, and saves
- Derived engagement and click-through rates
- `data_source=SYNTHETIC_DEMO` and `is_real_performance_data=false` on every row

## Business context used for example themes
The official Aquarium Park website describes aquatic exhibits, terrarium zones, bird experiences, waterfall views, fish feeding, family visits, and visitor planning. These public-facing themes informed the fictional captions and post themes only. No private analytics, account access, or real post performance was used.

Official site: https://aquariumpark.com.bd/

## Suggested feedback-loop demo
1. Import this file as synthetic historical sample data.
2. Display the dataset source badge on every analytics page and export.
3. Analyze post format, theme, platform, and goal across multiple records.
4. Generate knowledge records that include: observation, evidence, sample size, metric, confidence, and `evidence_type=synthetic`.
5. Generate a second content batch with those records as explicit inputs.
6. Show a “Why this was suggested” explanation linking each recommendation to synthetic evidence.
7. Keep all resulting recommendations marked as simulated. Do not describe them as verified Aquarium Park audience behavior or as proof that engagement will improve.

## Example derived insights (descriptive of this synthetic dataset only)
- Reel posts were intentionally assigned stronger engagement/click patterns than many static posts, so the UI can demonstrate format comparison.
- Posts with visit-planning or conversion goals include a range of click values, allowing a demo of CTA-oriented analysis.
- Question-style text posts have relatively low reach but comparatively visible comment activity, allowing a discussion of engagement mix.

These are properties of the generated sample, not real-world findings.

## Metric definitions
- `engagements = likes + comments + shares`
- `engagement_rate_by_reach_pct = engagements / reach * 100`
- `click_through_rate_by_impressions_pct = link_clicks / impressions * 100`

## Important safeguards
- Never merge these rows silently with real analytics.
- Never label generated records as “historical Aquarium Park data.”
- Preserve provenance fields through analysis and generated knowledge.
- If authorized real exports or API data become available, import them as a separate source and compare separately.
