<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;

/**
 * Signs users in against Okta over OpenID Connect.
 *
 * The callback keeps apart two questions that are easy to run together:
 * whether Okta can prove who this person is, and whether this person should
 * have an account here. A valid assertion only ever answers the first.
 */
class OktaController extends Controller
{
    public function login(): View
    {
        return view('auth.login');
    }

    public function redirect(): SymfonyRedirect
    {
        return Socialite::driver('okta')->redirect();
    }

    public function callback(): RedirectResponse
    {
        try {
            $oktaUser = Socialite::driver('okta')->user();
        } catch (InvalidStateException) {
            // The session died between the redirect and the callback, which in
            // practice means a stale tab or a cookie the browser dropped. Not
            // an error worth a page of its own; just start the flow again.
            return redirect()->route('login')
                ->with('status', 'That sign-in attempt expired. Please try again.');
        } catch (ClientException $e) {
            // Okta rejected the code-for-token exchange. These are almost
            // always configuration faults rather than anything the person at
            // the keyboard did: a secret that has been rotated, a redirect URI
            // that no longer matches, or a requirement the client does not
            // implement, DPoP being the one that catches people out. Okta puts
            // the reason in the response body, so log it verbatim and show the
            // user something that is not a stack trace.
            Log::error('Okta rejected the token exchange.', [
                'status' => $e->getResponse()->getStatusCode(),
                'body' => (string) $e->getResponse()->getBody(),
            ]);

            return redirect()->route('login')->with(
                'status',
                'Sign-in failed while exchanging the authorization code with Okta. This is a configuration problem rather than something you did; the reason is in the application log.'
            );
        }

        // Match on the subject claim first, falling back to email. The email
        // match is what links an account that already existed here, whether
        // seeded locally or pushed in by SCIM, to the Okta identity now
        // asserting it. After the first successful login, okta_id carries it.
        $user = User::firstWhere('okta_id', $oktaUser->getId())
            ?? User::firstWhere('email', $oktaUser->getEmail());

        if (! $user) {
            if (! config('services.okta.jit_provisioning')) {
                // Worth logging rather than swallowing: a run of these is
                // either a provisioning backlog or someone assigned to the
                // Okta app who was never meant to reach this environment.
                Log::info('Refused an unprovisioned Okta sign-in.', [
                    'okta_id' => $oktaUser->getId(),
                    'email' => $oktaUser->getEmail(),
                ]);

                return redirect()->route('login')->with(
                    'status',
                    'Okta verified who you are, but there is no account for you here yet. An administrator has to provision access first.'
                );
            }

            $user = new User([
                'name' => $oktaUser->getName() ?: $oktaUser->getNickname() ?: $oktaUser->getEmail(),
                'email' => $oktaUser->getEmail(),
            ]);
        }

        $user->okta_id = $oktaUser->getId();
        $user->last_sso_at = now();
        $user->save();

        Auth::login($user, remember: true);
        $request = request();
        $request->session()->regenerate();

        // Claims live in the session, not in columns on the users table. They
        // are true as of this login and no longer, and persisting them would
        // quietly promote a snapshot into a source of truth that nothing keeps
        // up to date.
        $request->session()->put('okta_claims', $oktaUser->user);

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Ends the session here only.
     *
     * This is deliberately not a single logout. The Okta session outlives it,
     * so "sign in" immediately afterwards will succeed without a prompt, which
     * looks like a bug and is not one. Ending the Okta session too means an
     * RP-initiated logout against the IdP, which is a separate round trip and
     * a separate decision: on a shared machine you want it, and in a tab
     * beside six other Okta apps you very much do not.
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'Signed out of this application.');
    }
}
