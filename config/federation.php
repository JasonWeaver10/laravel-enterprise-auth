<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Federated identity policy
    |--------------------------------------------------------------------------
    |
    | Rules that apply to anyone arriving from the identity provider, whatever
    | protocol carried them. OIDC and SAML differ in how an assertion is
    | transported and verified; they do not differ in who is allowed to be
    | here, so that decision lives in one place rather than once per protocol.
    |
    */

    /*
     * Whether a verified stranger gets an account created on first sign-in.
     *
     * Off means authentication and provisioning stay separate: the identity
     * provider can prove who someone is, and something else decides they
     * belong here. Okta itself works this way, refusing an authenticated user
     * who is not assigned to the application.
     *
     * On means the directory is treated as the sole source of truth and
     * everyone assigned to the app is meant to have access. That is a
     * legitimate choice, but it is a choice, and the cost is that a
     * misconfigured group assignment becomes indistinguishable from an
     * intentional grant.
     */
    'jit_provisioning' => env('FEDERATION_JIT_PROVISIONING', false),

];
