<?php

return [
    'phase' => 'Phase 5 · M04 Human-risk intelligence',
    'bootstrap_password' => env('CYBERAWARE_BOOTSTRAP_PASSWORD'),
    'modules' => [
        ['code' => 'M01', 'name' => 'SaaS foundation', 'description' => 'Organisations, access control and tenant isolation.', 'status' => 'Complete'],
        ['code' => 'M02', 'name' => 'Learning management', 'description' => 'Original courses, assessments and completion evidence.', 'status' => 'Complete'],
        ['code' => 'M03', 'name' => 'Safe simulations', 'description' => 'Authorised exercises with educational feedback.', 'status' => 'Complete'],
        ['code' => 'M04', 'name' => 'Human-risk intelligence', 'description' => 'Transparent indicators and programme trends.', 'status' => 'Complete'],
        ['code' => 'M05', 'name' => 'AI learning assistant', 'description' => 'Privacy-aware support with human content review.', 'status' => 'Planned'],
        ['code' => 'M06', 'name' => 'Reporting and audit', 'description' => 'Training evidence and management reporting.', 'status' => 'Planned'],
        ['code' => 'M07', 'name' => 'Enterprise integration', 'description' => 'Future identity, HR and learning connections.', 'status' => 'Planned'],
        ['code' => 'M08', 'name' => 'Commercial SaaS', 'description' => 'Plans, entitlements and controlled billing.', 'status' => 'Planned'],
    ],
];
