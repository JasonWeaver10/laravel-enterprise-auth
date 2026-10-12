<?php

namespace Tests\Feature;

use App\Auth\FederatedIdentity;
use App\Auth\FederatedUserResolver;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SamlServiceProviderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.saml2.sp_entityid', 'http://localhost:8000/auth/saml/metadata');
        config()->set('services.saml2.sp_acs', 'auth/saml/callback');
        config()->set('federation.jit_provisioning', false);
    }

    public function test_the_service_provider_publishes_its_own_metadata(): void
    {
        $response = $this->get('/auth/saml/metadata')
            ->assertOk()
            ->assertHeader('content-type', 'application/samlmetadata+xml');

        $xml = $response->getContent();

        $this->assertStringContainsString('EntityDescriptor', $xml);
        $this->assertStringContainsString('http://localhost:8000/auth/saml/metadata', $xml);
        $this->assertStringContainsString('http://localhost:8000/auth/saml/callback', $xml);

        // The binding matters: Okta posts the assertion through the browser,
        // so advertising only HTTP-Redirect would be wrong.
        $this->assertStringContainsString('HTTP-POST', $xml);
    }

    public function test_the_metadata_is_well_formed_xml(): void
    {
        $xml = $this->get('/auth/saml/metadata')->getContent();

        $previous = libxml_use_internal_errors(true);
        $document = simplexml_load_string($xml);
        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $this->assertNotFalse($document, 'Metadata did not parse as XML.');
        $this->assertSame([], $errors);
    }

    /**
     * Okta posts from its own origin with no session and no token, so the
     * route has to be exempt. This asserts the exemption is actually in place:
     * a tokenless POST should be rejected by SAML validation, not by CSRF.
     */
    public function test_the_assertion_endpoint_is_exempt_from_csrf(): void
    {
        $response = $this->post('/auth/saml/callback', ['SAMLResponse' => 'not-a-real-assertion']);

        $this->assertNotSame(419, $response->getStatusCode(), 'CSRF middleware is still guarding the ACS endpoint.');
        $response->assertRedirect('/login');
        $this->assertGuest();
    }

    /**
     * The endpoint is public, unauthenticated and CSRF-exempt, so anything at
     * all can be posted at it and none of it should produce a stack trace.
     *
     * @param  array<string, string>  $payload
     */
    #[DataProvider('junkPayloads')]
    public function test_unparseable_input_is_refused_without_a_server_error(array $payload): void
    {
        $response = $this->post('/auth/saml/callback', $payload);

        $this->assertLessThan(500, $response->getStatusCode(), 'A malformed assertion produced a server error.');
        $response->assertRedirect('/login');
        $this->assertGuest();
    }

    /**
     * @return array<string, array{array<string, string>}>
     */
    public static function junkPayloads(): array
    {
        return [
            'nothing at all' => [[]],
            'empty response' => [['SAMLResponse' => '']],
            'not base64' => [['SAMLResponse' => 'this is not base64 at all']],
            'base64 of nonsense' => [['SAMLResponse' => base64_encode('still not xml')]],
            'well-formed but wrong xml' => [['SAMLResponse' => base64_encode('<hello>there</hello>')]],
        ];
    }

    public function test_a_second_protocol_asserting_a_different_subject_does_not_repoint_the_account(): void
    {
        // Signed in over OIDC first, so the Okta user id is the stored subject.
        $user = User::factory()->create([
            'email' => 'jweaver@example.com',
            'okta_id' => '00u18mt70d0BaMXTh698',
        ]);

        // Now a SAML assertion arrives whose NameID is the email address,
        // which is what an Okta SAML app sends unless told otherwise.
        Log::shouldReceive('warning')->once()->withArgs(
            fn (string $message) => str_contains($message, 'Two subjects asserted')
        );

        $resolved = app(FederatedUserResolver::class)->resolve(new FederatedIdentity(
            subject: 'jweaver@example.com',
            email: 'jweaver@example.com',
            name: 'Jason Weaver',
            protocol: 'saml',
        ));

        $this->assertNotNull($resolved);
        $this->assertSame($user->id, $resolved->id);
        $this->assertSame('00u18mt70d0BaMXTh698', $resolved->okta_id, 'The original subject was overwritten.');
        $this->assertDatabaseCount('users', 1);
    }

    public function test_whitespace_around_an_asserted_value_does_not_orphan_the_match(): void
    {
        $user = User::factory()->create(['email' => 'padded@example.com', 'okta_id' => null]);

        // What an indented assertion actually hands over once the XML text
        // node is read.
        $resolved = app(FederatedUserResolver::class)->resolve(new FederatedIdentity(
            subject: "\n            padded@example.com\n        ",
            email: "\n            padded@example.com\n        ",
            name: '  Padded Person  ',
            protocol: 'saml',
        ));

        $this->assertNotNull($resolved, 'A padded email failed to match an existing account.');
        $this->assertSame($user->id, $resolved->id);
        $this->assertSame('padded@example.com', $resolved->okta_id);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_an_attribute_the_provider_sent_but_left_empty_counts_as_absent(): void
    {
        $identity = new FederatedIdentity(
            subject: '00uSOMEONE',
            email: '   ',
            name: "\n",
            protocol: 'saml',
        );

        $this->assertNull($identity->email);
        $this->assertNull($identity->name);
        $this->assertSame('00uSOMEONE', $identity->subject);
    }

    public function test_the_resolver_refuses_a_stranger_regardless_of_protocol(): void
    {
        Log::shouldReceive('info')->once()->withArgs(
            fn (string $message, array $context) => str_contains($message, 'Refused an unprovisioned')
                && $context['protocol'] === 'saml'
        );

        $resolved = app(FederatedUserResolver::class)->resolve(new FederatedIdentity(
            subject: 'stranger@example.com',
            email: 'stranger@example.com',
            protocol: 'saml',
        ));

        $this->assertNull($resolved);
        $this->assertDatabaseCount('users', 0);
    }
}
