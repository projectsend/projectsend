<?php
/*!
 * Hybridauth
 * https://hybridauth.github.io | https://github.com/hybridauth/hybridauth
 *  (c) 2017 Hybridauth authors | https://hybridauth.github.io/license.html
 */

namespace Hybridauth\Provider;

use Hybridauth\Adapter\OAuth2;
use Hybridauth\Data;
use Hybridauth\Exception\UnexpectedApiResponseException;
use Hybridauth\User;

/**
 * LinkedIn OAuth2 provider adapter.
 */
class LinkedIn extends OAuth2
{
    /**
     * {@inheritdoc}
     */
    public $scope = 'r_liteprofile r_emailaddress w_member_social';

    /**
     * {@inheritdoc}
     */
    protected $apiBaseUrl = 'https://api.linkedin.com/v2/';

    /**
     * {@inheritdoc}
     */
    protected $authorizeUrl = 'https://www.linkedin.com/oauth/v2/authorization';

    /**
     * {@inheritdoc}
     */
    protected $accessTokenUrl = 'https://www.linkedin.com/oauth/v2/accessToken';

    /**
     * {@inheritdoc}
     */
    protected $apiDocumentation = 'https://developer.linkedin.com/docs/oauth2';

    /**
     * {@inheritdoc}
     */
    public function getUserProfile()
    {
        $fields = [
            "id",
            "firstName",
            "lastName",
            "profilePicture(displayImage~:playableStreams)",
        ];


        $response = $this->apiRequest('me?projection=(' . implode(',', $fields) . ')');
        $data     = new Data\Collection($response);

        if (!$data->exists('id')) {
            throw new UnexpectedApiResponseException('Provider API returned an unexpected response.');
        }

        $userProfile = new User\Profile();

        $userProfile->identifier  = $data->get('id');
        $userProfile->firstName   = $data->filter('firstName')->filter('localized')->get('en_US');
        $userProfile->lastName    = $data->filter('lastName')->filter('localized')->get('en_US');
        $userProfile->photoURL    = $this->getUserPhotoUrl($data->filter('profilePicture')->filter('displayImage~')->get('elements'));
        $userProfile->email       = $this->getUserEmail();


        $userProfile->emailVerified = $userProfile->email;

        $userProfile->displayName = trim($userProfile->firstName . ' ' . $userProfile->lastName);

        return $userProfile;
    }

    /**
     * {@inheritdoc}
     */
    public function getUserPhotoUrl($elements)
    {
        $photoURL = '';
        if(is_array($elements)){
            // I need the largest picture that's why I am choosing end
            $element = end($elements);
            $photoURL = $element->identifiers[0]->identifier;
        }
        return $photoURL;
    }
    /**
     * {@inheritdoc}
     */
    public function getUserEmail()
    {
        $response = $this->apiRequest('emailAddress?q=members&projection=(elements*(handle~))');
        return isset($response->elements[0]->{'handle~'}->emailAddress) ? $response->elements[0]->{'handle~'}->emailAddress : "";
    }
    /**
     * {@inheritdoc}
     *
     * @see https://developer.linkedin.com/docs/share-on-linkedin
     */

    public function setUserStatus($status, $userID = null)
    {
        $status = is_string($status) ? array (
            'author' => 'urn:li:person:'.$userID,
            'lifecycleState' => 'PUBLISHED',
            'specificContent' =>
            array (
              'com.linkedin.ugc.ShareContent' =>
              array (
                'shareCommentary' =>
                array (
                  'text' => $status,
                ),
                'shareMediaCategory' => 'NONE',
              ),
            ),
            'visibility' =>
            array (
              'com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC',
            ),
          ) : $status;


        $headers = [
            'Content-Type' => 'application/json',
            'x-li-format'  => 'json',
            'X-Restli-Protocol-Version'  => '2.0.0',
        ];

        $response = $this->apiRequest("ugcPosts", 'POST', $status, $headers);

        return $response;
    }
}
