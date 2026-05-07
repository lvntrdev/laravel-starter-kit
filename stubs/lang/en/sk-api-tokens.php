<?php

return [
    'title' => 'API Tokens',
    'subtitle' => 'Personal Access Token Management',
    'token' => 'API Token',
    'create' => 'Create Token',
    'revoke' => 'Revoke Token',
    'revoke_confirm' => 'Are you sure you want to revoke this token? It will immediately stop working.',

    'fields' => [
        'name' => 'Token Name',
        'scopes' => 'Scopes',
        'user' => 'User',
        'expires_at' => 'Expires At',
    ],

    'token_modal' => [
        'title' => 'Personal Access Token',
        'description' => 'This is the only time your token will be displayed. Copy it now and store it securely. It cannot be retrieved after you close this dialog.',
        'copy' => 'Copy Token',
        'copied' => 'Copied!',
        'confirm' => 'I have saved the token',
    ],

    'created' => 'API token created successfully.',
    'revoked' => 'API token has been revoked.',
];
