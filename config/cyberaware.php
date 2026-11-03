<?php

return [
    'phase' => 'Phase 0',
    // No operational feature may be enabled through this configuration in Phase 0.
    'modules' => [
        ['code' => 'M01', 'name' => 'SaaS foundation', 'description' => 'Organisations, access control and tenant isolation.'],
        ['code' => 'M02', 'name' => 'Learning management', 'description' => 'Original courses, assessments and completion evidence.'],
        ['code' => 'M03', 'name' => 'Safe simulations', 'description' => 'Authorised exercises with educational feedback.'],
        ['code' => 'M04', 'name' => 'Human-risk intelligence', 'description' => 'Transparent indicators and programme trends.'],
        ['code' => 'M05', 'name' => 'AI learning assistant', 'description' => 'Privacy-aware support with human content review.'],
        ['code' => 'M06', 'name' => 'Reporting and audit', 'description' => 'Training evidence and management reporting.'],
        ['code' => 'M07', 'name' => 'Enterprise integration', 'description' => 'Future identity, HR and learning connections.'],
        ['code' => 'M08', 'name' => 'Commercial SaaS', 'description' => 'Plans, entitlements and controlled billing.'],
    ],
];
