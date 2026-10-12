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

### Two protocols will not agree on who someone is

OIDC asserts the Okta user id as `sub`, for example
`00u18mt70d0BaMXTh698`. A SAML app asserts whatever its application
username is set to, and the default is the email address. Same person, same
directory, two different identifiers.

The obvious fix is to make the SAML app assert the user id instead. It is not
available. Okta's Expression Language for Identity Engine exposes user profile
attributes, group affiliations, device profile attributes, entitlements,
session AMR and session id. The internal user id is not among them, so there
is no expression that produces it.

So this is a property of the integration rather than a misconfiguration, and
the application has to contain it. `FederatedUserResolver` matches on the
stored subject first, falls back to email once, and then refuses to overwrite a
subject it already trusts with a different one, logging the conflict instead.
Silently re-pointing an account to a new identifier is the failure worth
avoiding, because nothing downstream would ever tell you it happened.

### Okta changed its expression language, and both versions still work

Legacy attribute statements use `user.email`. The current Expression Language
for Identity Engine uses `user.profile.email`. Both are live in the product
simultaneously, the legacy table is hidden behind a "Show legacy
configuration" toggle, and claims moved from the app creation wizard to the
Sign On tab.

The practical consequence is that most instructions you find, including most
of what an LLM will tell you from memory, are written against whichever
version the author last used, and the wrong one fails with
`Invalid property email in expression user.email`, which does not hint that a
prefix is missing. Check which interface you are actually looking at before
trusting any example.

### The identity provider is not authoritative about everything it asserts

It is authoritative about *who* someone is. That is the whole point. It is not
automatically correct about the rest of the profile, and the rest of the profile
is where most of the attributes live.

A live sign-in against a fresh Okta org asserted
`"zoneinfo": "America/Los_Angeles"` for a user in Ohio. The profile had been
populated at sign-up from apparent location, and that location was a VPN exit
node. Nothing afterwards disagreed with it, so the identity provider went on
asserting it as fact.

That is the failure worth understanding. The attribute was not corrupted or
missing. It was captured once, from a signal that was misleading at the moment
of capture, and then became authoritative. An application rendering timestamps
from `zoneinfo` would show every user the wrong time, and the bug would be
genuinely hard to find, because the data is well formed and arrives from a
system you are supposed to trust.

Treat claims as input. Validate the ones you depend on.

## Tests

```
php artisan test
```

The tests mock Socialite rather than reaching Okta, because the handshake is
Socialite's and Okta's responsibility and both already test it. What is pinned
down here is what this application does with a valid assertion: who gets
created, who gets refused, and how a returning user is recognised.

## Roadmap

- [x] OIDC authorization code sign-in, verified against a live Okta org
- [x] SAML 2.0, SP-initiated, verified against a live Okta org
- [ ] SAML IdP-initiated, from the Okta dashboard tile
- [ ] SCIM 2.0 provisioning endpoints, including deactivation
- [ ] Okta group to application role mapping

## Deliberately not here

No password login, no registration, no reset flow. The point of federating
identity is to stop storing credentials, and leaving a local password door open
beside the front door defeats it.
