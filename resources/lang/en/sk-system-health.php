<?php

return [
    'title' => 'System Health',
    'subtitle' => 'Verify the environment and dependencies required by Starter Kit.',

    // Summary bar
    'summary_ok' => '{0} All checks passed|[1,*] :count checks passed',
    'summary_warn' => '{0} No warnings|[1,*] :count warning(s)',
    'summary_fail' => '{0} No failures|[1,*] :count failure(s)',
    'summary_total' => ':count total',

    // Badges / status labels
    'status_ok' => 'OK',
    'status_warn' => 'Warning',
    'status_fail' => 'Failed',

    // Error / warning banners
    'banner_fail_title' => ':name failed',
    'banner_warn_title' => ':name warning',
    'banner_hint_prefix' => 'Hint',

    // Buttons
    'run_button' => 'Re-run checks',
    'running' => 'Running…',

    // Flash / toast messages after run
    'run_ok' => 'All checks passed.',
    'run_warn' => ':count warning(s) detected. Review the details below.',
    'run_fail' => ':count check(s) failed. Immediate attention required.',

    // Table columns
    'col_check' => 'Check',
    'col_status' => 'Status',
    'col_message' => 'Message',
    'col_hint' => 'Hint',

    // Empty / error states
    'no_results' => 'No checks available.',
    'load_error' => 'Failed to load health data. Try re-running the checks.',

    // Generated at
    'generated_at' => 'Last checked at :time',
];
