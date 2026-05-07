<?php

return [
    'title' => 'API Clients',
    'subtitle' => 'OAuth2 Client Management',
    'client' => 'API Client',
    'create' => 'Create Client',
    'edit' => 'Edit Client',
    'revoke' => 'Revoke Client',
    'revoke_confirm' => 'Are you sure you want to revoke the client ":name"? All associated tokens will also be revoked.',

    'fields' => [
        'name' => 'Client Name',
        'grant_type' => 'Grant Type',
        'redirect_uris' => 'Redirect URIs',
        'redirect_uris_hint' => 'Enter each URI and press Enter or comma. Example: https://app.example.com/callback',
        'scopes' => 'Allowed Scopes',
        'confidential' => 'Confidential Client',
        'client_id' => 'Client ID',
        'client_secret' => 'Client Secret',
    ],

    'grant_types' => [
        'authorization_code' => 'Authorization Code',
        'client_credentials' => 'Client Credentials',
        'personal_access' => 'Personal Access',
    ],

    'secret_modal' => [
        'title' => 'Client Secret',
        'description' => 'This is the only time the client secret will be displayed. Copy it now and store it securely. It cannot be retrieved after you close this dialog.',
        'copy' => 'Copy Secret',
        'copied' => 'Copied!',
        'confirm' => 'I have saved the secret',
    ],

    'created' => 'API client created successfully.',
    'updated' => 'API client updated successfully.',
    'revoked' => 'API client has been revoked.',

    'errors' => [
        'fetch_failed_summary' => 'Load Error',
        'fetch_failed' => 'The client information could not be loaded. Please try again.',
    ],

    'validation' => [
        'grant_type_invalid' => 'The selected grant type is not supported.',
        'redirect_uri_https_required' => 'Redirect URIs must use HTTPS. Only localhost (127.0.0.1, ::1) may use HTTP.',
    ],
];
