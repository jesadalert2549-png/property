# Social sign-in setup

The login and registration pages support Google, Facebook, and Apple sign-in. Provider credentials are read from the PHP process environment; never put real secrets in a public repository.

## Redirect URLs

Register these exact callback URLs in each provider console:

- Google: `https://standardproperty.cloud/oauth.php?provider=google`
- Facebook: `https://standardproperty.cloud/oauth.php?provider=facebook`
- Apple: `https://standardproperty.cloud/oauth.php?provider=apple`

The callback host must match `SITE_URL` in `includes/config.php`. Apple requires HTTPS and a configured Services ID/return URL.

## Server environment values

The code first reads protected server environment values. For local XAMPP testing it also
reads matching values from `oauth_config.json`. Do not put real production secrets in a
publicly accessible file.

- `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`
- `FACEBOOK_CLIENT_ID`, `FACEBOOK_CLIENT_SECRET`, `FACEBOOK_GRAPH_VERSION` (defaults to `v23.0`)
- `APPLE_CLIENT_ID` (Services ID), `APPLE_TEAM_ID`, `APPLE_KEY_ID`, `APPLE_PRIVATE_KEY_PATH`

Set these in the web server's protected environment or hosting control panel, then restart/reload PHP. The Apple private key file must be outside the public web root, use an absolute path, and be readable by PHP.

On the first successful social sign-in, `oauth.php` creates the `oauth_identities` table if it does not exist. Google and Apple accounts with a verified matching email can be linked to an existing member account. Facebook accounts are not auto-linked by email; the member must use the existing sign-in method if that email is already registered.

Social sign-in also needs PHP cURL and OpenSSL enabled, outbound HTTPS access to the providers, and the provider callback URLs configured above. Google and Apple ID tokens are signature-checked against the providers' public keys. Google's server flow uses state and nonce checks as described in its [OpenID Connect documentation](https://developers.google.com/identity/openid-connect/openid-connect). Apple's callback uses `form_post` as described in its [Sign in with Apple REST API](https://developer.apple.com/documentation/signinwithapplerestapi).
