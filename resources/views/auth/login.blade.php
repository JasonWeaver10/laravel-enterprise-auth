@extends('layout')
@section('title', 'Sign in')

@section('body')
    @php
        $configured = filled(config('services.okta.client_id'))
            && filled(config('services.okta.client_secret'))
            && filled(config('services.okta.base_url'));
    @endphp

    <h1>Enterprise Auth</h1>
    <p class="lede">A Laravel application that federates identity to Okta.</p>

    @if ($configured)
        <div class="card">
            <h2 style="margin-top:0">OpenID Connect</h2>
            <p>You are redirected to Okta, authenticate there, and come back with an
               authorization code this application exchanges for tokens over a back channel.</p>
            <a class="btn" href="{{ route('okta.redirect') }}">Sign in with Okta</a>
        </div>

        <div class="card">
            <h2 style="margin-top:0">SAML 2.0</h2>
            @if (filled(config('services.saml2.metadata')))
                <p>Okta hands your browser a signed assertion to carry here. This
                   application verifies its signature, issuer, intended recipient,
                   validity window, and that the assertion has not been seen before.</p>
                <a class="btn" href="{{ route('saml.redirect') }}">Sign in with SAML</a>
            @else
                <p class="lede" style="margin-bottom:10px">
                    Not configured. Set <code>SAML2_IDP_METADATA_URL</code> to the
                    metadata URL from the Okta SAML app's Sign On tab.
                </p>
            @endif
            <p class="lede" style="margin-top:12px;margin-bottom:0;font-size:0.88rem">
                This application describes itself at
                <a href="{{ route('saml.metadata') }}">{{ route('saml.metadata') }}</a>,
                which is what you give Okta.
            </p>
        </div>
    @else
        <div class="status warn">
            <strong>Not configured yet.</strong>
            Set <code>OKTA_BASE_URL</code>, <code>OKTA_CLIENT_ID</code> and
            <code>OKTA_CLIENT_SECRET</code> in <code>.env</code>, then reload.
            See the README for where those come from.
        </div>
    @endif

    <h2>Provisioning</h2>
    <p class="lede" style="margin-bottom:0">
        Just-in-time provisioning is
        <strong>{{ config('federation.jit_provisioning') ? 'on' : 'off' }}</strong>.
        @if (config('federation.jit_provisioning'))
            Anyone Okta vouches for gets an account here on first sign-in.
        @else
            Okta can prove who you are, but an account has to exist here already.
            Authentication is not authorization to be here.
        @endif
    </p>
@endsection
