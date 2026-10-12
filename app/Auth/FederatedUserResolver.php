<?php

namespace App\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Decides whether an asserted identity gets a local account.
 *
 * Protocol-agnostic on purpose. Whether an assertion came over OIDC or SAML
 * changes how it was verified, not who is permitted to be here, and keeping a
 * copy of this rule per protocol is how the two quietly diverge until one of
 * them is a hole.
 */
class FederatedUserResolver
{
    /**
     * Returns the signed-in user, or null when they are not provisioned here.
     */
    public function resolve(FederatedIdentity $identity): ?User
    {
        $user = User::firstWhere('okta_id', $identity->subject);

        // Falling back to email links an account that existed here before the
        // identity provider ever asserted it, whether seeded locally or pushed
        // in by SCIM. It applies once: from then on the subject carries the
        // link, so a changed address cannot orphan the account.
        if (! $user && $identity->email) {
            $user = User::firstWhere('email', $identity->email);
        }

        if (! $user) {
            if (! config('federation.jit_provisioning')) {
                // A run of these is either a provisioning backlog or someone
                // assigned to the app in Okta who was never meant to reach
                // this environment. Both are worth knowing about.
                Log::info('Refused an unprovisioned federated sign-in.', [
                    'protocol' => $identity->protocol,
                    'subject' => $identity->subject,
                    'email' => $identity->email,
                ]);

                return null;
            }

            $user = new User([
                'name' => $identity->name ?: $identity->email ?: $identity->subject,
                'email' => $identity->email,
            ]);
        }

        // Two protocols can disagree about what identifies a person. OIDC
        // sends the Okta user id as "sub"; a SAML app sends whatever its
        // application username is set to, which defaults to the email address.
        // Overwriting a subject we already trust with a different one would
        // silently re-point an account, so refuse to and say so loudly. The
        // fix is at the identity provider, by configuring the SAML app to
        // assert a stable id rather than an address.
        if ($user->okta_id && $user->okta_id !== $identity->subject) {
            Log::warning('Two subjects asserted for one account; keeping the existing link.', [
                'user_id' => $user->id,
                'protocol' => $identity->protocol,
                'existing_subject' => $user->okta_id,
                'asserted_subject' => $identity->subject,
            ]);
        } else {
            $user->okta_id = $identity->subject;
        }

        $user->last_sso_at = now();
        $user->save();

        return $user;
    }
}
