<?php

return ['enabled' => env('SHARING_ENABLED', false), 'policy_version' => env('SHARING_POLICY_VERSION', 'draft-1'), 'retention_days' => max(1, min(365, (int) env('SHARING_RETENTION_DAYS', 30))),
    'purpose' => env('SHARING_PURPOSE', '')];
