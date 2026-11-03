# Betterlink CyberAware AI — Master Development Brief

## Project Authorisation

Create a completely new, independent software project called **Betterlink CyberAware AI**.

Developer: Betterlink Engineering.

Working repository name: `CyberAware-AI`.

The application must be a commercial, multi-tenant SaaS cybersecurity awareness, employee training, phishing simulation and human cyber-risk management platform.

**IMPORTANT: This is a new project.**

Do not access, modify, migrate, share databases with, or introduce dependencies on the existing ZimComply AI project.

ZimComply is being developed separately by Antigravity.

The new platform must be independently deployable and independently marketable.

## Product Vision

Build an affordable, enterprise-capable cybersecurity awareness and human-risk management platform designed initially for Zimbabwean and Southern African organisations, with international expansion capabilities.

The platform should help organisations:

1. Train employees on cybersecurity risks.
2. Conduct assessments and measure knowledge.
3. Run safe, authorised phishing simulations.
4. Identify awareness gaps.
5. Assign remedial training.
6. Produce auditable training and risk reports.
7. Track cybersecurity awareness programmes across departments and branches.
8. Demonstrate evidence of employee cybersecurity training for internal and external audits.

Do not copy proprietary source code, copyrighted course material, trademarks, interfaces or content belonging to KnowBe4 or other competitors.

## Technology Stack

- Laravel 12.
- PHP 8.4.
- PostgreSQL 16.
- React with TypeScript.
- Inertia.js.
- Tailwind CSS v4.
- Laravel queues, scheduler, policies and notifications.
- Laravel AI SDK where appropriate.
- Private document and media storage.
- REST APIs for approved integrations.
- Responsive web application with PWA readiness.

Use a modular monolith with clear domain boundaries.

## Core Product Modules

### M01 — SaaS Foundation

Implement multi-tenancy, organisations, departments, branches, users, invitations, role-based permissions, subscription entitlements, audit trails, tenant isolation and secure authentication.

Support platform administrators, tenant administrators, training managers, security officers, departmental managers, learners and auditors.

### M02 — Learning Management

Implement:

- Course catalogue.
- Lessons and learning paths.
- Multimedia lessons.
- Quizzes and assessments.
- Passing scores.
- Course assignments.
- Progress tracking.
- Training reminders.
- Completion certificates.
- Certificate verification.
- Employee learning dashboard.
- Administrator reporting.

Support English initially, with architecture for Shona, Ndebele and additional languages.

### M03 — Phishing Simulation

Implement a controlled, permission-based phishing awareness simulation system.

Requirements:

- Explicit organisation administrator authorisation.
- Approved simulation scope and recipient lists.
- Campaign creation and approval.
- Safe simulation templates.
- Controlled delivery scheduling.
- Training-only landing pages.
- Simulation event tracking.
- Post-simulation educational feedback.
- Automatic remedial training assignments.
- Campaign reporting.

Security restrictions:

- Do not collect or store actual passwords, MFA codes, session tokens or banking credentials.
- Do not imitate real authentication systems in a way that enables credential harvesting.
- Do not support malicious attachments, executable payloads or evasion mechanisms.
- Do not allow unrestricted public-target campaigns.
- Apply recipient verification, rate limits, suppression lists and delivery safeguards.
- Maintain campaign approval and audit records.
- Do not implement phishing email sending in Phase 0.

### M04 — Human Cyber-Risk Intelligence

Implement configurable awareness indicators, learning completion metrics, simulation outcomes, risk trends and management dashboards.

Risk scoring must be explainable, configurable and transparent.

Do not treat a simulation click as proof that an employee is malicious or negligent.

### M05 — AI Learning Assistant

Use AI to assist with:

- Explaining cybersecurity concepts.
- Recommending relevant training.
- Drafting original educational content.
- Generating quiz drafts for human review.
- Summarising approved training outcomes.

AI-generated content must be reviewed before publication.

Apply privacy controls, data minimisation, tenant isolation and AI-provider configuration.

### M06 — Reporting and Audit

Provide:

- Training completion reports.
- Assessment results.
- Departmental summaries.
- Campaign effectiveness reports.
- Remedial training reports.
- Audit evidence exports.
- Certificate verification.
- Executive dashboards.

### M07 — Enterprise Integration

Design future integrations with Microsoft Entra ID, Microsoft 365, Active Directory, SAML/OIDC SSO, HR systems and external learning platforms.

Do not claim these integrations are operational before implementation and verification.

### M08 — Commercial SaaS

Design subscription tiers for small businesses, medium enterprises and large organisations.

Support per-user pricing, annual plans, trials, module entitlements, customer onboarding and controlled billing.

Do not integrate live payment gateways during Phase 0.

## Market Requirements

Design for:

- SMEs.
- Banks and microfinance institutions.
- Insurance companies.
- Mining companies.
- Schools and universities.
- Healthcare organisations.
- Manufacturing.
- Logistics.
- Government and public institutions.
- Professional services.

The system must support organisations with limited IT capacity and low-bandwidth environments.

## Security Requirements

Implement defence-in-depth, secure tenant isolation, MFA readiness, strict authorisation, encryption, audit logging, rate limiting, secure media access, secrets management and safe background jobs.

Conduct threat modelling for phishing simulations, employee privacy, tenant isolation and AI use.

Adopt relevant OWASP guidance and design for applicable Zimbabwean data-protection obligations.

## Development Governance

Develop in independently approved phases.

Before every phase:

1. Inspect the repository and Git state.
2. Review the master specification.
3. Confirm the authorised phase.
4. Run baseline tests.
5. Identify migration and compatibility risks.

After every phase:

1. Run automated tests.
2. Run TypeScript analysis.
3. Run the production build.
4. Conduct appropriate browser acceptance testing.
5. Update implementation documentation.
6. Commit verified changes.
7. Report limitations and outstanding work.
8. Stop and await approval.

Do not automatically start subsequent phases.

## Phase 0 — Authorised Work Only

**Proceed with Phase 0 only.**

Tasks:

1. Inspect the local development environment.
2. Verify PHP, Composer, Node.js, npm, PostgreSQL and Git.
3. Create the new project in an isolated directory.
4. Establish the Laravel, React, TypeScript, Inertia and Tailwind foundation.
5. Configure a dedicated development database without affecting existing databases.
6. Create the project architecture and domain boundaries.
7. Draft the database entity relationship design.
8. Define the tenant-isolation strategy.
9. Define roles and permissions.
10. Define the safe phishing simulation architecture and threat model.
11. Define the learning management architecture.
12. Define AI governance requirements.
13. Create the phased implementation roadmap.
14. Establish testing and CI conventions.
15. Create README.md and docs/IMPLEMENTATION_STATUS.md.
16. Run framework smoke tests, TypeScript analysis and the Vite build where applicable.

Do not implement the full learning system, phishing campaign delivery, AI features, subscriptions or enterprise integrations during Phase 0.

Do not run destructive commands against existing projects or databases.

Do not deploy to production.

## Completion Report

Provide:

- Environment verification.
- Project directory and repository status.
- Technology versions.
- Architecture summary.
- Proposed modules.
- Database design.
- Security and threat model.
- Test and build results.
- Phased roadmap.
- Known limitations.
- Git commit hash, if committed.

**STOP after Phase 0 and wait for approval before Phase 1.**