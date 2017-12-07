<?php

/**
 * Class SAML2_HTTPRedirectTest
 */
class SAML2_HTTPRedirectTest extends PHPUnit_Framework_TestCase
{

    /**
     * test parsing of basic query string with authnrequest and
     * verify that the correct issuer is found.
     */
    public function testRequestParsing()
    {
        $qs = 'SAMLRequest=pVJNjxMxDP0ro9yn88FOtRu1lcpWiEoLVNvCgQtKE6eNlHGG2IHl35OZLmLZQy%2BcrNh%2Bz88vXpDq%2FSDXic%2F4CN8TEBdPvUeSU2EpUkQZFDmSqHogyVru1x8eZDur5RADBx28eAG5jlBEENkFFMV2sxTfzO0dmKa11namPuoc39hba%2BfqpqlbM6%2Fb5mZ%2B1LWtj6L4ApEycikyUYYTJdgisULOqbrpyqYt67tD28iulV33VRSbvI1DxRPqzDyQrCrAk0OYUYpWB4QnnqGvVN4fkJ2emitnhoocnjyU5E5YjnrXf6TfB6TUQ9xD%2FOE0fH58%2BEueHbHOv2Yn1w8eRneqPpiU68M5DxjfdIltqTRNWQNWJc8lDaLYPfv71qHJaq5be7w0kXx%2FOOzK3af9QawWI7ecrIqr%2F9HYAyujWL2SuKheDlhcbuljlrbd7IJ3%2BlfxLsRe8XXlY8aZ0k6tkqNCcvkzsuXeh5%2F3ERTDUnBMIKrVZeS%2FF7v6DQ%3D%3D';
        $_SERVER['QUERY_STRING'] = $qs;

        $hr = new SAML2_HTTPRedirect();
        $request = $hr->receive();
        $this->assertInstanceOf('SAML2_Request', $request);
        $issuer = $request->getIssuer();
        $this->assertEquals('https://profile.surfconext.nl/simplesaml/module.php/saml/sp/metadata.php/default-sp', $issuer);
    }

    /**
     * test parsing of basic query string with saml response and
     * verify that the correct issuer is found.
     */
    public function testResponseParsing()
    {
        $qs = 'SAMLResponse=vVdbc6rIGn2fX2G5H1MJd0RrJ1UgKmDQIIiXl1NANzcRCA2i%2FvppNLpNJsnMnqo5T9qL%2Fq7r62bxEznbJO%2FNIMqzFMHWfpukqHcCH9tVkfYyB0WolzpbiHql1zNF%2FblHP5C9vMjKzMuS9o3J9xYOQrAooyxtt1T5sd2fzqxplxVIhoIMCbkuIEnehSRJ0xRJdroU1fFc6HWA4Hiw3bJhgbDtYxu7wg4QqqCaotJJSwyRFHdP0fcUb1Fsj2V7pLBut2SIyih1ypNVWJY56hFEGW6iexSBh7y8Z4WHqiweUFX4XpJV4CFNiC1Mkiwl8gyVl57gaOnlv5U9tv9HdzsSTQ4GlCB0hqQ8xD84XYHmOzxFMkOh%2FfSz6UbvlGTxdAkN0yBK4UOJ0zrHzFK4L5ugTlWGMC0j75QsEYEc51E6wCmdn8Stq59ntszSKSv0ftXPAGzZTlLB71lAp909s%2FI8iFCbeDpHeO%2B0J164emN3j6JzD3EddV0%2F1MxDVgQETZIUsdSfTS%2BEW%2Bc%2BOhHSsHWx%2Bnuj22HoMgwA0KV53GHKFQTHcR2PZT0SQEHgfAggECB0%2F8Uw%2FHeMANzLKMBjVhWX0wO%2BKpskyC6B9wAUBT%2FaT3%2B0WhdzCNTUz07e%2Bk6apThwEh1PwXVYhhloiUmQFVEZbr9sKUU2vu%2Fh3rv3KDb9gbnFEX7FOKX4D729y7RAzj0KHerssHE3gz4sIGa6NZ%2Bpj%2B0fv0ffqUyrcFLkZ8UWvV%2F%2BXmow3cEkyyHAZ%2Fqtwmakf8vhp537Sfw1RzkK8KT8mw6%2Bde%2BXk9NBfdou75YRhJU%2FUXO3XN7ZQjh2p%2FmwFsXHUwK3m0%2FAtfHn5YfRubJ8tuhXCeETSyl2wWg%2BX5aA3K4SL6ZhcjciFlLVCUcCKUOKMRcSuwfiRCIPSyXNd5bvktb%2BTkUvuwUi2CWnOgdt42XqgZ75x2dIB0p3bB61VyllUn4Z18mEu6P8TjGtzLkrBfOIOGp70Y6D9apEu1gJkklHFgVlM1TvFra%2FP2SvSubm0kE6slSaB%2FPHazk3%2Bf%2FR1DSGh2t9S47syvgIXhf95pLym1MKn3RV7d8d%2B31xawZirUpioGrii7Z7jg00m7GRLpKjvvk6MlWXkY2BJBlzUR%2BS%2B%2F5R1KRgYkviyhI3nK7PxFoOVrJtGOqgBjZQtGRFh%2BQNrnyBjzFu2bY2crc2xoN6eMblQd0l18sJqUfScbWgkDqaJF5q1EroTXRrXutHldQtA%2F8OmEWDxSdsf8ViCegGqvvGyd9oUGtTSx4YusiORGo%2B6Eu6Yi9nh%2FVikoEbXNp%2FjvdDXZlTtjnbcgnGV7q0OuHiXn8BI%2FsIZDXw6GHp9qV4vdRIXR35H%2Fsn4v5hdxNR7kuRMZYCo78fr4p0IUzqVzCrZ7WjVJjGZ74bGENLc4GfieZzuIog7U6jTDtoNeXfeeIuj1HCmvYq3w0QVGG5VITnIN90xh3mZSNrzwIYBiwaxMMhHwfbuJgOTCbbE0FpgS4g7PpFUYndi%2BqNDhRybY3NB%2Fow4YAwmorHTNtMFuAl7tY2W%2BzYasdwunMQalUWDVHKWKWPayN0iWzqB3JgLCTJslTnpc7HOSZYgKpuHiez9Tw2SzeKcUNqzMGMjCV1pGDbQfDd%2FtdhmA%2BFemkNnnVxc%2BYk1PvWpt4PZHF6nrvAkiib9LZ27CjGDe59gWcYn9jzzbpaL439SBYXZ1y3ZGaWeIxxUJVJ6C7qYEXbB6B%2BPAf1qdZBbQx1UZdEX6hlY6WNs7Ua7ryJaAyGkiHikR6IY2Gws%2Bbkc6BoyKzwhTeF22CW5LnuayKcbqtqPQlNfUUbYbUdTte5I7rCZKjW8%2FHcPhy01Mw6G35TKv2x2mWRgbodnmbp0JLl1aBeaHJXCUUkvk4zmpo7QrC2GIGotzwNmXFQjJe7NIlFd%2FyylJeazjqbY%2BfAK3y926kji%2FdJn4y0hfLKsHFdn2%2BQj5fCFTxfG8TthfLuxnkTCGblxtAr31YTrJ5UuTXEbwCn%2FF5WNUgE7v3T1l7ZvDgiLCDaT6TDsb7LdEm%2Bi2Uiw3DAYSDLkKxP%2BRzPOMDlXZ73%2BTdZcQ75Ppt%2BlvpR47fRY%2BfXz%2FfJeNueC50CFu2vHTUNaU2ycppOC9EvYfEX5dQ9y%2BgZ9KK8qeX%2FLKIvyvSz5D88eqsS7wBR8xg1hUkQkwE%2F04MdXNU%2FqPwihSsQNW9cnH1ZRN45%2FLsnT7%2FZlw9K8urmw%2FpdQOJDhdcUyjBtlDvcYoZap%2BVXSpjucRyu3MSyH3v4sgE03WOZHi382qqmAO4xZZwLuC5Pczzrk5zHeRTPAMahXExcx6V8yDGMA7sQeKB9mx5OusSy%2BhOon%2BBvQixpnr79bPR6XrMPwy%2F4p84KcG3UJ65%2BRbno9zRoVo1YO1yZwpIxxaL%2BwceHFj6k2Y3Hz8w%2BCfgOuzJwRS%2FfT9fPq8vwP%2F0J';
        $_SERVER['QUERY_STRING'] = $qs;

        $hr = new SAML2_HTTPRedirect();
        $request = $hr->receive();
        $this->assertInstanceOf('SAML2_Response', $request);
        $issuer = $request->getIssuer();
        $this->assertEquals('https://engine.test.surfconext.nl/authentication/idp/metadata', $issuer);
    }

    /**
     * test parsing of Relaystate and SAMLencoding together with authnrequest
     */
    public function testRequestParsingMoreParams()
    {
        $request = 'SAMLRequest=pVJNb9swDP0rhu6O7XjeGiEJkDYoGqDbgibboZdCkahEgEx5Ir11%2F36y02FdD7n0JPDjPT4%2BcU6q9Z1c9XzCB%2FjRA3H23HokORYWoo8ogyJHElULJFnL3erzvZxOStnFwEEHL15BLiMUEUR2AUW2WS%2FEUw2NrXRp7NWshEPVzJqm%2BTQzVV1DddC21rUy1tq6norsO0RKyIVIRAlO1MMGiRVySpVVk1fTvKr25ZVsGvnh46PI1mkbh4pH1Im5I1kUgEeHMKE%2BWh0QnnmCvlBpf0B2emwunOkKcnj0kJM7Yj7oXf2VfhOQ%2BhbiDuJPp%2BHbw%2F0%2F8uSIdf4tO7m28zC4U7TB9KnendKAIabzO82VpjFrwKrec06dyLYv%2Fl47NEnNZWsP5yaSd%2Fv9Nt9%2B3e3Fcj5wy9GquHyPxhZYGcXqjcR58XrA%2FHxLX5K0zXobvNO%2Fs9sQW8WXlQ8ZZ3I7tkqOCsmlz0iWex9%2B3URQDAvBsQdRLM8j%2F7%2FY5R8%3D&RelayState=https%3A%2F%2Fprofile.surfconext.nl%2F&SAMLEncoding=urn%3Aoasis%3Anames%3Atc%3ASAML%3A2.0%3Abindings%3AURL-Encoding%3ADEFLATE';
        $_SERVER['QUERY_STRING'] = $request;

        $hr = new SAML2_HTTPRedirect();
        $request = $hr->receive();
        $this->assertInstanceOf('SAML2_Request', $request);
        $relaystate = $request->getRelayState();
        $this->assertEquals('https://profile.surfconext.nl/', $relaystate);
    }

    /**
     * Test parsing a signed authentication request.
     * It does not actually verify the validity of the signature.
     */
    public function testSignedRequestParsing()
    {
        $qs = 'SAMLRequest=nVLBauMwEP0Vo7sjW7FpKpJA2rBsoNuGOruHXhZFHm8EsuRqxtv27yvbWWgvYelFgjfvzbx5zBJVazu56enkHuG5B6TktbUO5VhYsT446RUalE61gJK0rDY%2F7qSYZbILnrz2ln2QXFYoRAhkvGPJbrtiv7VoygJEoTJ9LOusXDSFuJ4vdH6cxwoIEGUjsrqoFUt%2BQcCoXLHYKMoRe9g5JOUoQlleprlI8%2FyQz6W4ksXiiSXbuI1xikbViahDyfkRSM2wD40DmjnL0bSdhcE6Hx7BTd3xqnqoIPw1GmbdqWPJNx80jCGtGIUeWLL5t8mtd9i3EM78n493%2FzWr9XVvx%2B58mj39IlUaR%2FQmKOPq4Dtkyf4c9E1EjPtzOePjREL5%2FXDYp%2FuH6sDWy6G3HDML66%2B5ayO7VlHx2dySf2y9nM7pPprabffeGv02ZNcquux5QEydNiNVUlAODTiKMVvrX24DKIJz8nw9jfx8tOt3&RelayState=https%3A%2F%2Fbeta.surfnet.nl%2Fsimplesaml%2Fmodule.php%2Fcore%2Fauthenticate.php%3Fas%3DBraindrops&SigAlg=http%3A%2F%2Fwww.w3.org%2F2000%2F09%2Fxmldsig%23rsa-sha1&Signature=b%2Bqe%2FXGgICOrEL1v9dwuoy0RJtJ%2FGNAr7gJGYSJzLG0riPKwo7v5CH8GPC2P9IRikaeaNeQrnhBAaf8FCWrO0cLFw4qR6msK9bxRBGk%2BhIaTUYCh54ETrVCyGlmBneMgC5%2FiCRvtEW3ESPXCCqt8Ncu98yZmv9LIVyHSl67Se%2BfbB9sDw3%2FfzwYIHRMqK2aS8jnsnqlgnBGGOXqIqN3%2Bd%2F2dwtCfz14s%2F9odoYzSUv32qfNPiPez6PSNqwhwH7dWE3TlO%2FjZmz0DnOeQ2ft6qdZEi5ZN5KCV6VmNKpkrLMq6DDPnuwPm%2F8oCAoT88R2jG7uf9QZB%2BArWJKMEhDLsCA%3D%3D';
        $_SERVER['QUERY_STRING'] = $qs;

        $hr = new SAML2_HTTPRedirect();
        $request = $hr->receive();
        $this->assertInstanceOf('SAML2_Request', $request);
        $relaystate = $request->getRelayState();
        $this->assertEquals('https://beta.surfnet.nl/simplesaml/module.php/core/authenticate.php?as=Braindrops', $relaystate);
    }

    /**
     * test that a request with unsupported encoding specified fails
     */
    public function testInvalidEncodingSpecified()
    {
        $qs = 'SAMLRequest=pVJNb9swDP0rhu6O7XjeGiEJkDYoGqDbgibboZdCkahEgEx5Ir11%2F36y02FdD7n0JPDjPT4%2BcU6q9Z1c9XzCB%2FjRA3H23HokORYWoo8ogyJHElULJFnL3erzvZxOStnFwEEHL15BLiMUEUR2AUW2WS%2FEUw2NrXRp7NWshEPVzJqm%2BTQzVV1DddC21rUy1tq6norsO0RKyIVIRAlO1MMGiRVySpVVk1fTvKr25ZVsGvnh46PI1mkbh4pH1Im5I1kUgEeHMKE%2BWh0QnnmCvlBpf0B2emwunOkKcnj0kJM7Yj7oXf2VfhOQ%2BhbiDuJPp%2BHbw%2F0%2F8uSIdf4tO7m28zC4U7TB9KnendKAIabzO82VpjFrwKrec06dyLYv%2Fl47NEnNZWsP5yaSd%2Fv9Nt9%2B3e3Fcj5wy9GquHyPxhZYGcXqjcR58XrA%2FHxLX5K0zXobvNO%2Fs9sQW8WXlQ8ZZ3I7tkqOCsmlz0iWex9%2B3URQDAvBsQdRLM8j%2F7%2FY5R8%3D&RelayState=https%3A%2F%2Fprofile.surfconext.nl%2F&SAMLEncoding=urn%3Aoasis%3Anames%3Atc%3ASAML%3A2.0%3Abindings%3AURL-Encoding%3Anone';
        $_SERVER['QUERY_STRING'] = $qs;

        $this->setExpectedException('Exception', 'Unknown SAMLEncoding:');
        $hr = new SAML2_HTTPRedirect();
        $request = $hr->receive();
    }

    public function testNoSigAlgSpecified()
    {
        $qs = 'SAMLRequest=nVLBauMwEP0Vo7sjW7FpKpJA2rBsoNuGOruHXhZFHm8EsuRqxtv27yvbWWgvYelFgjfvzbx5zBJVazu56enkHuG5B6TktbUO5VhYsT446RUalE61gJK0rDY%2F7qSYZbILnrz2ln2QXFYoRAhkvGPJbrtiv7VoygJEoTJ9LOusXDSFuJ4vdH6cxwoIEGUjsrqoFUt%2BQcCoXLHYKMoRe9g5JOUoQlleprlI8%2FyQz6W4ksXiiSXbuI1xikbViahDyfkRSM2wD40DmjnL0bSdhcE6Hx7BTd3xqnqoIPw1GmbdqWPJNx80jCGtGIUeWLL5t8mtd9i3EM78n493%2FzWr9XVvx%2B58mj39IlUaR%2FQmKOPq4Dtkyf4c9E1EjPtzOePjREL5%2FXDYp%2FuH6sDWy6G3HDML66%2B5ayO7VlHx2dySf2y9nM7pPprabffeGv02ZNcquux5QEydNiNVUlAODTiKMVvrX24DKIJz8nw9jfx8tOt3&RelayState=https%3A%2F%2Fbeta.surfnet.nl%2Fsimplesaml%2Fmodule.php%2Fcore%2Fauthenticate.php%3Fas%3DBraindrops&Signature=b%2Bqe%2FXGgICOrEL1v9dwuoy0RJtJ%2FGNAr7gJGYSJzLG0riPKwo7v5CH8GPC2P9IRikaeaNeQrnhBAaf8FCWrO0cLFw4qR6msK9bxRBGk%2BhIaTUYCh54ETrVCyGlmBneMgC5%2FiCRvtEW3ESPXCCqt8Ncu98yZmv9LIVyHSl67Se%2BfbB9sDw3%2FfzwYIHRMqK2aS8jnsnqlgnBGGOXqIqN3%2Bd%2F2dwtCfz14s%2F9odoYzSUv32qfNPiPez6PSNqwhwH7dWE3TlO%2FjZmz0DnOeQ2ft6qdZEi5ZN5KCV6VmNKpkrLMq6DDPnuwPm%2F8oCAoT88R2jG7uf9QZB%2BArWJKMEhDLsCA%3D%3D';
        $_SERVER['QUERY_STRING'] = $qs;

        $this->setExpectedException('Exception', 'Missing signature algorithm');
        $hr = new SAML2_HTTPRedirect();
        $request = $hr->receive();
    }

    /**
     * test handling of non-deflated data in samlrequest
     */
    public function testInvalidRequestData()
    {
        $qs = 'SAMLRequest=cannotinflate';
        $_SERVER['QUERY_STRING'] = $qs;

        $oldwarning = PHPUnit_Framework_Error_Warning::$enabled;
        PHPUnit_Framework_Error_Warning::$enabled = false;
        $this->setExpectedException('Exception', 'Error while inflating');
        $hr = new SAML2_HTTPRedirect();
        $request = @$hr->receive();
        PHPUnit_Framework_Error_Warning::$enabled = $oldwarning;
    }

    /**
     * test with a query string that has Request nor Response
     */
    public function testNoRequestOrResponse()
    {
        $qs = 'aap=noot&mies=jet&wim&RelayState=etc';
        $_SERVER['QUERY_STRING'] = $qs;

        $this->setExpectedException('Exception', 'Missing SAMLRequest or SAMLResponse parameter.');
        $hr = new SAML2_HTTPRedirect();
        $request = $hr->receive();
    }

    /**
     * Construct an authnrequest and send it.
     */
    public function testSendAuthnrequest()
    {
        $request = new SAML2_AuthnRequest();
        $hr = new SAML2_HTTPRedirect();
        $hr->send($request);
    }

    /**
     * Construct an authnresponse and send it.
     * Also test setting a relaystate and destination for the response.
     */
    public function testSendAuthnResponse()
    {
        $response = new SAML2_Response();
        $response->setIssuer('testIssuer');
        $response->setRelayState('http://example.org');
        $response->setDestination('http://example.org/login?success=yes');
        $hr = new SAML2_HTTPRedirect();
        $hr->send($response);
    }

}
