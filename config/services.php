<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'okta' => [
        'client_id' => env('OKTA_CLIENT_ID'),
        'client_secret' => env('OKTA_CLIENT_SECRET'),
        'redirect' => env('OKTA_REDIRECT_URI', '/auth/okta/callback'),

        // Your org URL, e.g. https://dev-12345678.okta.com
        'base_url' => env('OKTA_BASE_URL'),

        // Empty selects the org authorization server at /oauth2/v1, which
        // every org has. Setting it to 'default' selects the default custom
        // authorization server at /oauth2/default/v1, which needs API Access
        // Management. The org server is the safer default for a repo someone
        // else clones, and groups do not depend on the choice: the OIDC app
        // carries its own groups claim setting on its Sign On tab.
        //
        // Worth knowing while debugging either one: Okta answers for every
        // *.okta.com host, so a wrong or non-existent org subdomain returns a
        // branded 400 with no error body, identical to what a misconfigured
        // authorization server returns. Check the hostname before the config.
        'auth_server_id' => env('OKTA_AUTH_SERVER_ID', ''),

        // Authentication and provisioning are separate questions. Okta can
        // prove who someone is without that implying they should have an
        // account here. Left off, a stranger who authenticates successfully is
        // still refused, and SCIM remains the only thing that creates users.
        // Turn it on only where the directory is the sole source of truth and
        // every assigned user is meant to have access.
        'jit_provisioning' => env('OKTA_JIT_PROVISIONING', false),
    ],

    'saml2' => [
        // SAML has no client id and secret. Each side publishes a metadata
        // document describing itself, and trust comes from exchanging them.
        // Okta's URL is on the SAML app's Sign On tab, labelled as the
        // identity provider metadata.
        'metadata' => env('SAML2_IDP_METADATA_URL'),
        'ttl' => env('SAML2_IDP_METADATA_TTL', 86400),

        // Ours. The entity id is just a stable name, not a URL that has to
        // resolve, but pointing it at our own metadata document is the
        // convention and saves anyone configuring this a guess.
        'sp_entityid' => env('SAML2_SP_ENTITY_ID', env('APP_URL', 'http://localhost:8000').'/auth/saml/metadata'),
        'sp_acs' => 'auth/saml/callback',

        // Refuse an unsigned assertion. The entire security model is the
        // signature: without it, anything that can reach the endpoint can
        // claim to be anyone.
        'sp_sign_assertions' => true,

        // Okta sends no attribute statements until they are configured on the
        // app's SAML settings. The plain names are what you type there; the
        // long URIs are what federation products emit by convention, and are
        // listed so this works against something other than Okta too.
        'attribute_map' => [
            'email' => [
                'email',
                'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress',
                'urn:oid:0.9.2342.19200300.100.1.3',
            ],
            'name' => [
                'displayName',
                'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/name',
            ],
            'first_name' => [
                'firstName',
                'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/givenname',
            ],
            'last_name' => [
                'lastName',
                'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/surname',
            ],
        ],
    ],

];
