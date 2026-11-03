# AI governance requirements

Design only. No AI SDK, provider account, key, network call or assistant endpoint is enabled.
Evaluate Laravel AI SDK compatibility with Laravel 12 and PHP 8.4 when its phase is approved;
do not install an incompatible dependency or upgrade the framework without approval.

Allowed future uses: explaining concepts, suggesting approved courses, drafting original
lessons and quiz questions, summarising approved aggregate training outcomes. The AI cannot
send simulations, change roles, approve content, decide disciplinary action or charge customers.

Tenant administrators explicitly enable an approved provider, region, retention policy and
budget. Keep keys in a secret manager, referenced by tenant configuration, never client props.
Minimise context: no credentials, raw simulation tokens, sensitive identifiers or unnecessary
employee records. Prefer de-identified aggregates. Document vendor retention/training terms
and contractual controls before activation. Provider failure must leave learning available.

Retrieval is restricted to tenant-authorised, published sources and policy-checked every time.
Treat documents, prompts and generated output as untrusted. No arbitrary URL fetching,
shell execution or open-ended tool use. Enforce output schema/size and request rate/budget
limits; escape rendered text. Retrieval filters are tested for cross-tenant leakage.

All generated content remains a draft until a qualified human reviews accuracy, originality,
regional relevance, accessibility, language, privacy and safe advice. Store provenance, model
version, source references, reviewer, review date and publication version. A reviewer cannot
bypass the publication state machine. Include an AI-assisted label and an error-report route.

Evaluation gates: concept accuracy, unsafe instruction handling, prompt injection, privacy,
tenant separation, biased/punitive interpretations, hallucinated citations, draft publication
bypass and provider outage. Audit metadata is minimised; raw prompt retention is off by
default. A tenant kill switch stops requests. Human operators can retract published errors.
