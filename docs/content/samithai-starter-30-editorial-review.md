# Samith AI — Starter 30 Prompt Products

**Bundle ID:** samithai-starter-2026-11  
**Editorial state:** Drafts, NOT tested against live AI providers, NOT submitted, NOT published.  
**Default price/license:** US$0.00 / regular license (subject to approved platform terms).  
**Target:** Existing Laravel Prompt Manager (/dashboard/prompts for administrators, /account/prompts for creators).  
**Public storefront layouts:** Unchanged.

## Category inventory

| Category | Count | File |
|---|---:|---|
| Marketing & Social Media | 6 | 01-marketing-social-media.json |
| Education & Teaching | 5 | 02-education-teaching.json |
| Business & Productivity | 5 | 03-business-productivity.json |
| Coding & Development | 5 | 04-coding-development.json |
| AI Image & Video | 4 | 05-ai-image-video.json |
| E-commerce & Sales | 3 | 06-ecommerce-sales.json |
| Data & Research | 2 | 07-data-research.json |
| **Total** | **30** | |

All JSON files reside in database/content/starter-2026-11/.

## Complete editorial inventory

### Marketing & Social Media
1. 30-Day Social Media Content Planner
2. Facebook Ad Campaign Copy Architect
3. Customer Persona & Value Proposition Builder
4. LinkedIn Thought Leadership Post Studio
5. SEO Content Brief & Search Intent Mapper
6. Customer Service Response Playbook

### Education & Teaching
7. 16-Week University Course Planner
8. Differentiated Classroom Lesson Plan Designer
9. Quiz, Assessment & Rubric Generator
10. English–Khmer Bilingual Teaching Material Builder
11. Student Feedback & Learning Support Coach

### Business & Productivity
12. Business Plan & Feasibility Architect
13. Standard Operating Procedure (SOP) Builder
14. Meeting Minutes & Action Tracker
15. Project Risk Register & Mitigation Planner
16. Professional Proposal & Executive Summary Writer

### Coding & Development
17. Laravel Secure Code Review Assistant
18. Full-Stack Feature Specification Builder
19. Debugging & Root Cause Investigator
20. API Documentation & Test Case Generator
21. Accessible UI/UX Audit Checklist

### AI Image & Video
22. AI Product Photography Creative Director
23. Brand Identity Moodboard Prompt Designer
24. Cinematic Video Storyboard Planner
25. Short-Form Social Video Script Builder

### E-commerce & Sales
26. Product Listing Optimization Suite
27. Sales Discovery & Follow-Up Assistant
28. Marketplace Category & Tag Taxonomy Planner

### Data & Research
29. Research Survey & Questionnaire Designer
30. Survey Data Analysis & Interpretation Planner

## Product content format

Each product contains:
- A professional title, unique starter-2026- slug and full human-facing summary.
- A reusable master prompt with role, objective, variable inputs, ordered methodology, structured output and safety guardrails.
- An editorial test scenario and three or more checks in the source JSON. These are not Prompt database fields and are retained for human QA.
- Zero price, regular license, draft status, null publication timestamp and no featured image. The owner can add approved imagery later.

No model-generated outputs, analytics, live test passes, quotes or business results are claimed.

## Read-only preflight and intentional import

The command is available ONLY after a separately authorized source deployment. It is not executed by DatabaseSeeder, the installer, scheduler or a website request.

Use an existing administrator or creator email; run from the authorized Laravel application:

    php artisan samithai:import-starter-drafts --check --author-email="EXISTING_ADMIN_OR_CREATOR_EMAIL"

This validates seven source files, 30 distinct detailed prompts, category availability and existing-slug conflicts WITHOUT writing to the database.

After verifying a suitable MySQL backup, intentionally import:

    php artisan samithai:import-starter-drafts --apply --author-email="EXISTING_ADMIN_OR_CREATOR_EMAIL"

Import is transactional and only inserts missing category rows and free, unpublished draft prompts. Existing records are never overwritten. Matching drafts are skipped when rerun. The command cannot submit, approve, publish or enable paid checkout.

## Editor acceptance for EVERY product

1. Replace bracketed inputs with a realistic sanitized test case; never use confidential customer data.
2. Run the prompt with the intended AI provider and model. No model has been tested or claimed by this bundle.
3. Check every requested heading, clarity, useful specificity and non-fabrication of citations, figures or results.
4. Verify factual correctness, cultural tone, bias, confidentiality and accessibility. English–Khmer products require native-language review.
5. Test a second independent input, noting provider, model, test date and any edits.
6. Update the draft in Prompt Manager if needed and rerun the review.
7. Submit only after passing review. Human administrator approval and actual publication remain separate actions.

## Security, audit and rollback

- This content release requires no database migrations, frontend layout changes, billing changes or automatic publication.
- Import writes to the database; keep a verified database backup before --apply.
- If an import transaction fails, attempted inserts roll back. Previously existing content is preserved.
- To reverse a successful import, review ownership and existing references before removing drafts. Never mass-delete without inspecting order and review relationships.
- Keep the JSON files as the original editorial reference until all drafts pass human review.
