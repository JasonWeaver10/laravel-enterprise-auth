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
    public string $subject;

    public ?string $email;

    public ?string $name;

    /** @var array<string, mixed> */
    public array $raw;

    public string $protocol;

    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        string $subject,
        ?string $email = null,
        ?string $name = null,
        array $raw = [],
        string $protocol = 'oidc',
    ) {
        // SAML attribute values are XML text nodes, so an identity provider
        // that indents its assertion hands over values wrapped in whitespace.
        // An email with a trailing newline matches no row, and the failure
        // presents as "this account does not exist" rather than as a parsing
        // problem, which is a genuinely annoying afternoon.
        $this->subject = trim($subject);
        $this->email = self::clean($email);
        $this->name = self::clean($name);
        $this->raw = $raw;
        $this->protocol = $protocol;
    }

    /**
     * Treats a value that is only whitespace as absent, since that is what it
     * means: an attribute the identity provider sent but did not populate.
     */
    private static function clean(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
