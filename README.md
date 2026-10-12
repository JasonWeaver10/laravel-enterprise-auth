# Laravel Enterprise Auth

A Laravel application that federates identity to Okta. It is a reference
implementation rather than a tutorial: the interesting part is not the OIDC
handshake, which is four lines of Socialite, but the decisions you are forced
to make once a stranger can prove who they are.

Runs with `composer install` and `php artisan serve`. No build step.

## Setup

1. Create a free org at [developer.okta.com](https://developer.okta.com/signup).
2. **Applications → Create App Integration → OIDC → Web Application.**

   | Field | Value |
   |---|---|
   | Grant type | Authorization Code |
   | Sign-in redirect URI | `http://localhost:8000/auth/okta/callback` |
   | Sign-out redirect URI | `http://localhost:8000` |

3. Copy the credentials into `.env`:

   ```
   OKTA_BASE_URL=https://integrator-0000000.okta.com
   OKTA_CLIENT_ID=
   OKTA_CLIENT_SECRET=
   OKTA_AUTH_SERVER_ID=default
   OKTA_JIT_PROVISIONING=false
   ```

4. `php artisan migrate && php artisan serve`

`OKTA_AUTH_SERVER_ID` selects Okta's default *custom* authorization server
rather than the org server. The custom one can be taught to emit a groups
claim; the org server cannot, and group-to-role mapping needs it.

## The decisions

### Authentication is not authorization to exist here

The question "can Okta prove who this is" and the question "should this person
have an account here" are different, and conflating them is how an identity
integration quietly becomes an access control problem. Every Okta tutorial
ends at `firstOrCreate`, which answers the second question by accident.

`OKTA_JIT_PROVISIONING` is **off** by default. A user Okta vouches for who has
no local account is refused, and the attempt is logged. Provisioning is a
separate channel: SCIM, a seeder, or an administrator. Turn JIT on only where
the directory is genuinely the sole source of truth and everyone assigned to
the app is meant to have access.

The tradeoff is real in both directions. JIT off means a provisioning backlog
locks people out of an app they are legitimately assigned to. JIT on means a
misconfigured Okta group assignment is indistinguishable from an intentional
grant.

### The subject claim is the identifier, not the email address

Emails change. People marry, companies rename their domain, someone moves from
contractor to staff. Matching a returning user on email means that when the
address changes you create a second account and orphan the first, taking their
history with it.

So the match is `okta_id` first, which is the `sub` claim and never changes,
and email only as a fallback. The email fallback exists for exactly one moment:
the first sign-in of an account that was provisioned here before Okta ever saw
it. After that, `okta_id` carries the link. There is a test for this.

### Claims belong in the session, not in columns

What the IdP asserts is true as of that login. Writing group membership or a
job title onto the users table turns a snapshot into a source of truth that
nothing afterwards keeps current, and stale authorization data is worse than
none because it is trusted.

### Signing out here does not sign you out of Okta

The logout route ends the local session only. The Okta session outlives it, so
signing in again succeeds with no prompt. This looks like a bug and is not one.

Ending the IdP session as well means an RP-initiated logout, which is a
separate round trip and a separate product decision: on a shared machine you
want it, and in a browser tab beside six other Okta apps you very much do not.
Doing it silently in the name of tidiness logs people out of everything.

### Federated accounts have no password

`password` is nullable. A user who only ever arrives through Okta never sets
one, and a placeholder hash in that column reads as a credential to whoever
audits the table in two years.

## Tests

```
php artisan test
```

The tests mock Socialite rather than reaching Okta, because the handshake is
Socialite's and Okta's responsibility and both already test it. What is pinned
down here is what this application does with a valid assertion: who gets
created, who gets refused, and how a returning user is recognised.

## Roadmap

- [x] OIDC authorization code sign-in
- [ ] SAML 2.0, both SP-initiated and IdP-initiated
- [ ] SCIM 2.0 provisioning endpoints, including deactivation
- [ ] Okta group to application role mapping

## Deliberately not here

No password login, no registration, no reset flow. The point of federating
identity is to stop storing credentials, and leaving a local password door open
beside the front door defeats it.
