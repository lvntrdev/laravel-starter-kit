<?php

use Lvntr\StarterKit\Http\Requests\Admin\Settings\UpdateStorageSettingsRequest;

/*
| The storage form renders endpoint/URL inputs behind a fixed `https://`
| addon, so scheme-less hosts reach the request; the scheme is added before
| `url` validation runs.
*/

it('adds https to scheme-less storage endpoints and urls', function (): void {
    $request = UpdateStorageSettingsRequest::create('/', 'PUT', [
        'hetzner_endpoint' => ' fsn1.your-objectstorage.com/ ',
        'hetzner_url' => 'bucket.fsn1.your-objectstorage.com',
        'spaces_endpoint' => 'https://fra1.digitaloceanspaces.com',
        'aws_url' => 'http://cdn.example.test',
        'aws_endpoint' => '   ',
    ]);

    (new ReflectionMethod($request, 'prepareForValidation'))->invoke($request);

    expect($request->input('hetzner_endpoint'))->toBe('https://fsn1.your-objectstorage.com')
        ->and($request->input('hetzner_url'))->toBe('https://bucket.fsn1.your-objectstorage.com')
        ->and($request->input('spaces_endpoint'))->toBe('https://fra1.digitaloceanspaces.com')
        ->and($request->input('aws_url'))->toBe('http://cdn.example.test')
        ->and($request->input('aws_endpoint'))->toBeNull();
});
