<?php

namespace App\Http\Controllers\Auth;

use App\Auth\FederatedIdentity;
use App\Auth\FederatedUserResolver;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use LightSaml\Error\LightSamlException;
use LightSaml\Model\Context\SerializationContext;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;
use Throwable;

/**
 * Signs users in against Okta over SAML 2.0.
 *
 * The shape of the problem differs from OIDC in a way worth noting. OIDC asks
 * the identity provider a question over a back channel and reads the answer.
 * SAML has the identity provider hand the browser a signed document to carry
 * here, so everything rests on verifying that document: its signature, who
 * issued it, who it was addressed to, when it was valid for, and whether it
 * has been presented before.
 */
class SamlController extends Controller
{
    public function redirect(): SymfonyRedirect
    {
        return Socialite::driver('saml2')->redirect();
    }

    public function callback(Request $request, FederatedUserResolver $resolver): RedirectResponse
    {
        try {
            // Stateless, because an identity-provider-initiated sign-in posts
            // here without ever having visited this application first, so
            // there is no stored state to compare against. SAML does not
            // depend on a state parameter the way OAuth does: the assertion is
            // signed, addressed to one recipient, valid only inside a time
            // window, and its id is checked against replay. Those four are
            // what make this safe, not a round trip through the session.
            $samlUser = Socialite::driver('saml2')->stateless()->user();
        } catch (LightSamlException $e) {
            // A well-formed assertion that failed validation: bad signature,
            // wrong issuer, wrong recipient, outside its validity window, or
            // an id already seen. These messages are the only useful
            // diagnostic while configuring an identity provider.
            Log::warning('Rejected a SAML assertion.', [
                'reason' => $e->getMessage(),
                'type' => $e::class,
            ]);

            return redirect()->route('login')->with(
                'status',
                $this->rejectionMessage($e),
            );
        } catch (Throwable $e) {
            // Anything that was not even a parseable assertion. This endpoint
            // is public, unauthenticated, and exempt from CSRF, so absolutely
            // anything can be posted at it, and lightsaml throws plain
            // argument exceptions on malformed input rather than its own
            // types. Without this, junk in the SAMLResponse field returns a
            // stack trace to whoever sent it.
            Log::warning('Could not parse a SAML assertion.', [
                'reason' => $e->getMessage(),
                'type' => $e::class,
            ]);

            return redirect()->route('login')->with(
                'status',
                'That request did not contain a readable SAML assertion.',
            );
        }

        $user = $resolver->resolve(new FederatedIdentity(
            subject: $samlUser->getId(),
            email: $samlUser->getEmail(),
            name: $samlUser->getName(),
            raw: $this->readableAttributes($samlUser->getRaw()),
            protocol: 'saml',
        ));

        if (! $user) {
            return redirect()->route('login')->with(
                'status',
                'Okta verified who you are, but there is no account for you here yet. An administrator has to provision access first.'
            );
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        $request->session()->put('okta_claims', $this->readableAttributes($samlUser->getRaw()));
        $request->session()->put('okta_protocol', 'SAML 2.0');

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Publishes this application's SAML metadata.
     *
     * SAML does not have a client id and secret. Both sides describe
     * themselves in a metadata document instead, and trust is established by
     * exchanging those. This is our half: entity id, where assertions should
     * be posted, and which bindings we accept.
     */
    public function metadata(): Response
    {
        $descriptor = Socialite::driver('saml2')->getServiceProviderEntityDescriptor();

        $context = new SerializationContext;
        $descriptor->serialize($context->getDocument(), $context);

        return response($context->getDocument()->saveXML(), 200, [
            'Content-Type' => 'application/samlmetadata+xml',
        ]);
    }

    /**
     * Describes a rejection without handing details to a stranger.
     *
     * The reason is genuinely useful while wiring an identity provider up, and
     * genuinely none of an unauthenticated caller's business once it is
     * running: telling someone exactly why their forged assertion failed is
     * free help for the next attempt. So it is shown in debug and withheld
     * otherwise, with the full reason going to the log either way.
     */
    private function rejectionMessage(LightSamlException $e): string
    {
        return config('app.debug')
            ? 'That SAML assertion was rejected. Reason: '.$e->getMessage()
            : 'That SAML assertion was rejected. The reason has been logged.';
    }

    /**
     * Turns lightsaml Attribute objects into something printable.
     *
     * @param  array<int|string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function readableAttributes(array $attributes): array
    {
        $readable = [];

        foreach ($attributes as $key => $attribute) {
            if (is_object($attribute) && method_exists($attribute, 'getName')) {
                $values = method_exists($attribute, 'getAllAttributeValues')
                    ? $attribute->getAllAttributeValues()
                    : [];

                $readable[$attribute->getName()] = count($values) === 1 ? reset($values) : $values;

                continue;
            }

            $readable[$key] = $attribute;
        }

        return $readable;
    }
}
