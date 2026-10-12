<?php

namespace App\Http\Controllers\Auth;

use App\Auth\FederatedIdentity;
use App\Auth\FederatedUserResolver;
use App\Http\Controllers\Controller;
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
 * What happens to a valid assertion is not decided here. That rule is shared
 * with the SAML path and lives in FederatedUserResolver, because the protocol
 * changes how an identity was verified, not who is allowed to be here.
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

    public function callback(Request $request, FederatedUserResolver $resolver): RedirectResponse
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

        $user = $resolver->resolve(new FederatedIdentity(
            subject: $oktaUser->getId(),
            email: $oktaUser->getEmail(),
            name: $oktaUser->getName() ?: $oktaUser->getNickname(),
            raw: (array) $oktaUser->user,
            protocol: 'oidc',
        ));

        if (! $user) {
            return redirect()->route('login')->with(
                'status',
                'Okta verified who you are, but there is no account for you here yet. An administrator has to provision access first.'
            );
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        // Claims live in the session, not in columns on the users table. They
        // are true as of this login and no longer, and persisting them would
        // quietly promote a snapshot into a source of truth that nothing keeps
        // up to date.
        $request->session()->put('okta_claims', (array) $oktaUser->user);
        $request->session()->put('okta_protocol', 'OpenID Connect');

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
