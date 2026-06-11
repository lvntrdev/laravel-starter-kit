<?php

return [
    'title' => 'Log Files',
    'subtitle' => 'Read and manage Laravel log files',
    'filename' => 'Filename',
    'channel' => 'Channel',
    'channel_daily' => 'daily',
    'channel_single' => 'single',
    'channel_other' => 'other',
    'size' => 'Size',
    'modified' => 'Modified',
    'active' => 'Active',
    'active_yes' => 'Active',
    'back_to_list' => 'Back to list',
    'all' => 'All',

    // List view
    'search_files' => 'Search filename',
    'channel_all' => 'All',
    'clear_filters' => 'Clear filters',
    'no_files_title' => 'No matching log files',
    'no_files_sub' => 'Review your search or channel filter.',
    'per_page' => 'Per page',
    'showing_files_range' => 'Showing :from–:to of :total',

    // Filters
    'level' => 'Level',
    'from' => 'From',
    'to' => 'To',
    'search_messages' => 'Search in messages',
    'keyword_placeholder' => 'Keyword, class, file…',
    'all_levels' => 'All levels',
    'apply' => 'Apply',
    'reset' => 'Reset',
    'load_more' => 'Load more',
    'expand_all' => 'Expand all',
    'collapse_all' => 'Collapse all',
    'entry_count' => ':count entries',
    'stacktrace' => 'stacktrace',
    'no_entries' => 'No entries match the filters',
    'no_entries_sub' => 'Review the level, time range or search criteria.',
    'showing_n_entries' => 'Showing :count entries',
    'eof' => 'End of file',

    // Delete
    'delete_selected' => 'Delete selected',
    'delete_confirm' => 'Delete log file ":name"? This cannot be undone.',
    'deleted_count' => ':count log file(s) deleted.',
    'failed_count' => ':count log file(s) could not be deleted:',

    // Failure reasons (must match DeleteLogFilesAction reason codes)
    'reason_invalid_filename' => 'invalid filename',
    'reason_not_found' => 'not found',
    'reason_active_file_protected' => 'active file is protected',
    'reason_delete_failed' => 'delete failed',

    // Server error keys (referenced from PHP exceptions)
    'invalid_filename' => 'Invalid log filename.',
    'file_not_found' => 'Log file not found.',
    'active_file_protected' => 'Active log files cannot be deleted.',
    'read_failed' => 'Could not read the log file.',
];
