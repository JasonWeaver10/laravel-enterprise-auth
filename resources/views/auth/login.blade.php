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
            <p>Sign in with the identity provider. You will be sent to Okta, authenticate there, and return here.</p>
            <a class="btn" href="{{ route('okta.redirect') }}">Sign in with Okta</a>
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
        <strong>{{ config('services.okta.jit_provisioning') ? 'on' : 'off' }}</strong>.
        @if (config('services.okta.jit_provisioning'))
            Anyone Okta vouches for gets an account here on first sign-in.
        @else
            Okta can prove who you are, but an account has to exist here already.
            Authentication is not authorization to be here.
        @endif
    </p>
@endsection
