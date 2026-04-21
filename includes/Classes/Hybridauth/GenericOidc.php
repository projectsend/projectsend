<?php

namespace ProjectSend\Classes\Hybridauth;

use Hybridauth\Adapter\OAuth2;
use Hybridauth\Exception\InvalidApplicationCredentialsException;
use Hybridauth\Exception\UnexpectedApiResponseException;
use Hybridauth\Data;
use Hybridauth\User;

/**
 * Generic OpenID Connect provider.
 *
 * Works with any standards-compliant OIDC server (Keycloak, Authentik,
 * Authelia, Dex, Okta, etc.) by auto-discovering endpoints from the
 * issuer's /.well-known/openid-configuration document.
 *
 * Required config:
 *   'issuer_url' => 'https://auth.example.com/realms/myrealm'
 *   'keys'       => ['id' => '...', 'secret' => '...']
 */
class GenericOidc extends OAuth2
{
    public $scope = 'openid profile email';

    protected function configure()
    {
        parent::configure();

        if (!$this->config->exists('issuer_url') || !$this->config->get('issuer_url')) {
            throw new InvalidApplicationCredentialsException(
                'You must define an issuer_url for the OIDC provider.'
            );
        }

        $issuer = rtrim($this->config->get('issuer_url'), '/');
        $discovery = $this->fetchDiscovery($issuer);

        $this->apiBaseUrl       = $discovery['userinfo_endpoint'];
        $this->authorizeUrl     = $discovery['authorization_endpoint'];
        $this->accessTokenUrl   = $discovery['token_endpoint'];
    }

    private function fetchDiscovery(string $issuer): array
    {
        $url = $issuer . '/.well-known/openid-configuration';

        $response = $this->httpClient->request($url);
        $data = json_decode($response, true);

        if (empty($data['authorization_endpoint']) || empty($data['token_endpoint']) || empty($data['userinfo_endpoint'])) {
            throw new UnexpectedApiResponseException(
                'OIDC discovery document at ' . $url . ' is missing required fields.'
            );
        }

        return $data;
    }

    public function getUserProfile()
    {
        $response = $this->apiRequest($this->apiBaseUrl);
        $data = new Data\Collection($response);

        if (!$data->exists('sub')) {
            throw new UnexpectedApiResponseException('Provider API returned an unexpected response.');
        }

        $userProfile = new User\Profile();

        $userProfile->identifier   = $data->get('sub');
        $userProfile->email        = $data->get('email');
        $userProfile->firstName    = $data->get('given_name');
        $userProfile->lastName     = $data->get('family_name');
        $userProfile->displayName  = $data->get('preferred_username') ?: $data->get('name');
        $userProfile->emailVerified = (bool) $data->get('email_verified');

        return $userProfile;
    }
}
