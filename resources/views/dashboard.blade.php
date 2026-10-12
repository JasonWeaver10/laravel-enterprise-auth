@extends('layout')
@section('title', 'Signed in')

@section('body')
    <h1>Signed in</h1>
    <p class="lede">Federated through Okta over {{ session('okta_protocol', 'an unknown protocol') }}.</p>

    <div class="card">
        <h2 style="margin-top:0">Local account</h2>
        <dl>
            <dt>Name</dt><dd>{{ auth()->user()->name }}</dd>
            <dt>Email</dt><dd>{{ auth()->user()->email }}</dd>
            <dt>Okta subject</dt><dd>{{ auth()->user()->okta_id ?? 'not linked' }}</dd>
            <dt>Last SSO</dt><dd>{{ auth()->user()->last_sso_at?->diffForHumans() ?? 'never' }}</dd>
            <dt>Password set</dt><dd>{{ auth()->user()->password ? 'yes' : 'no, federated only' }}</dd>
        </dl>
    </div>

    <div class="card">
        <h2 style="margin-top:0">What the identity provider asserted</h2>
        <p class="lede" style="margin-bottom:12px">
            What the identity provider asserted. Held in the session rather than
            written to the users table, because it is true as of this login and
            nothing keeps it current afterwards.
        </p>
        <pre>{{ json_encode(session('okta_claims', []), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
    </div>

    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit" class="btn ghost">Sign out</button>
    </form>
    <p class="lede" style="margin-top:10px;font-size:0.88rem">
        Ends the session here only. The Okta session outlives it, so signing in
        again will not prompt you.
    </p>
@endsection
