<?php

namespace App\Auth;

/**
 * One asserted identity, with the protocol that carried it stripped away.
 *
 * OIDC arrives as a claims array over a back channel; SAML arrives as signed
 * XML posted through the browser. The application needs the same three facts
 * from either, so normalising here keeps the protocol differences at the edge
 * where they belong.
 */
final readonly class FederatedIdentity
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $subject,
        public ?string $email = null,
        public ?string $name = null,
        public array $raw = [],
        public string $protocol = 'oidc',
    ) {}
}
