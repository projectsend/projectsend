<?php

/**
 * Class SAML2_BindingTest
 */
class SAML2_BindingTest extends PHPUnit_Framework_TestCase
{

    /**
     * Test getting binding objects from string.
     */
    public function testGetBinding()
    {
        $bind = SAML2_Binding::getBinding(SAML2_Const::BINDING_HTTP_POST);
        $this->assertInstanceOf('SAML2_HTTPPost', $bind);
        $bind = SAML2_Binding::getBinding(SAML2_Const::BINDING_HTTP_REDIRECT);
        $this->assertInstanceOf('SAML2_HTTPRedirect', $bind);
        $bind = SAML2_Binding::getBinding(SAML2_Const::BINDING_HTTP_ARTIFACT);
        $this->assertInstanceOf('SAML2_HTTPArtifact', $bind);
        $bind = SAML2_Binding::getBinding(SAML2_Const::BINDING_HOK_SSO);
        $this->assertInstanceOf('SAML2_HTTPPost', $bind);

        $this->setExpectedException('Exception', 'Unsupported binding:');
        $bind = SAML2_Binding::getBinding('nonsense');
    }

    /**
     * Test binding guessing functionality.
     */
    public function testBindingGuesserGET()
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = array('SAMLRequest' => 'pVJNjxMxDP0ro9yn88FOtRu1lcpWiEoLVNvCgQtKE6eNlHGG2IHl35OZLmLZQy%2BcrNh%2Bz88vXpDq%2FSDXic%2F4CN8TEBdPvUeSU2EpUkQZFDmSqHogyVru1x8eZDur5RADBx28eAG5jlBEENkFFMV2sxTfzO0dmKa11namPuoc39hba%2BfqpqlbM6%2Fb5mZ%2B1LWtj6L4ApEycikyUYYTJdgisULOqbrpyqYt67tD28iulV33VRSbvI1DxRPqzDyQrCrAk0OYUYpWB4QnnqGvVN4fkJ2emitnhoocnjyU5E5YjnrXf6TfB6TUQ9xD%2FOE0fH58%2BEueHbHOv2Yn1w8eRneqPpiU68M5DxjfdIltqTRNWQNWJc8lDaLYPfv71qHJaq5be7w0kXx%2FOOzK3af9QawWI7ecrIqr%2F9HYAyujWL2SuKheDlhcbuljlrbd7IJ3%2BlfxLsRe8XXlY8aZ0k6tkqNCcvkzsuXeh5%2F3ERTDUnBMIKrVZeS%2FF7v6DQ%3D%3D');
        $bind = SAML2_Binding::getCurrentBinding();
        $this->assertInstanceOf('SAML2_HTTPRedirect', $bind);

        $_GET = array('SAMLResponse' => 'vVdbc6rIGn2fX2G5H1MJd0RrJ1UgKmDQIIiXl1NANzcRCA2i%2FvppNLpNJsnMnqo5T9qL%2Fq7r62bxEznbJO%2FNIMqzFMHWfpukqHcCH9tVkfYyB0WolzpbiHql1zNF%2FblHP5C9vMjKzMuS9o3J9xYOQrAooyxtt1T5sd2fzqxplxVIhoIMCbkuIEnehSRJ0xRJdroU1fFc6HWA4Hiw3bJhgbDtYxu7wg4QqqCaotJJSwyRFHdP0fcUb1Fsj2V7pLBut2SIyih1ypNVWJY56hFEGW6iexSBh7y8Z4WHqiweUFX4XpJV4CFNiC1Mkiwl8gyVl57gaOnlv5U9tv9HdzsSTQ4GlCB0hqQ8xD84XYHmOzxFMkOh%2FfSz6UbvlGTxdAkN0yBK4UOJ0zrHzFK4L5ugTlWGMC0j75QsEYEc51E6wCmdn8Stq59ntszSKSv0ftXPAGzZTlLB71lAp909s%2FI8iFCbeDpHeO%2B0J164emN3j6JzD3EddV0%2F1MxDVgQETZIUsdSfTS%2BEW%2Bc%2BOhHSsHWx%2Bnuj22HoMgwA0KV53GHKFQTHcR2PZT0SQEHgfAggECB0%2F8Uw%2FHeMANzLKMBjVhWX0wO%2BKpskyC6B9wAUBT%2FaT3%2B0WhdzCNTUz07e%2Bk6apThwEh1PwXVYhhloiUmQFVEZbr9sKUU2vu%2Fh3rv3KDb9gbnFEX7FOKX4D729y7RAzj0KHerssHE3gz4sIGa6NZ%2Bpj%2B0fv0ffqUyrcFLkZ8UWvV%2F%2BXmow3cEkyyHAZ%2Fqtwmakf8vhp537Sfw1RzkK8KT8mw6%2Bde%2BXk9NBfdou75YRhJU%2FUXO3XN7ZQjh2p%2FmwFsXHUwK3m0%2FAtfHn5YfRubJ8tuhXCeETSyl2wWg%2BX5aA3K4SL6ZhcjciFlLVCUcCKUOKMRcSuwfiRCIPSyXNd5bvktb%2BTkUvuwUi2CWnOgdt42XqgZ75x2dIB0p3bB61VyllUn4Z18mEu6P8TjGtzLkrBfOIOGp70Y6D9apEu1gJkklHFgVlM1TvFra%2FP2SvSubm0kE6slSaB%2FPHazk3%2Bf%2FR1DSGh2t9S47syvgIXhf95pLym1MKn3RV7d8d%2B31xawZirUpioGrii7Z7jg00m7GRLpKjvvk6MlWXkY2BJBlzUR%2BS%2B%2F5R1KRgYkviyhI3nK7PxFoOVrJtGOqgBjZQtGRFh%2BQNrnyBjzFu2bY2crc2xoN6eMblQd0l18sJqUfScbWgkDqaJF5q1EroTXRrXutHldQtA%2F8OmEWDxSdsf8ViCegGqvvGyd9oUGtTSx4YusiORGo%2B6Eu6Yi9nh%2FVikoEbXNp%2FjvdDXZlTtjnbcgnGV7q0OuHiXn8BI%2FsIZDXw6GHp9qV4vdRIXR35H%2Fsn4v5hdxNR7kuRMZYCo78fr4p0IUzqVzCrZ7WjVJjGZ74bGENLc4GfieZzuIog7U6jTDtoNeXfeeIuj1HCmvYq3w0QVGG5VITnIN90xh3mZSNrzwIYBiwaxMMhHwfbuJgOTCbbE0FpgS4g7PpFUYndi%2BqNDhRybY3NB%2Fow4YAwmorHTNtMFuAl7tY2W%2BzYasdwunMQalUWDVHKWKWPayN0iWzqB3JgLCTJslTnpc7HOSZYgKpuHiez9Tw2SzeKcUNqzMGMjCV1pGDbQfDd%2FtdhmA%2BFemkNnnVxc%2BYk1PvWpt4PZHF6nrvAkiib9LZ27CjGDe59gWcYn9jzzbpaL439SBYXZ1y3ZGaWeIxxUJVJ6C7qYEXbB6B%2BPAf1qdZBbQx1UZdEX6hlY6WNs7Ua7ryJaAyGkiHikR6IY2Gws%2Bbkc6BoyKzwhTeF22CW5LnuayKcbqtqPQlNfUUbYbUdTte5I7rCZKjW8%2FHcPhy01Mw6G35TKv2x2mWRgbodnmbp0JLl1aBeaHJXCUUkvk4zmpo7QrC2GIGotzwNmXFQjJe7NIlFd%2FyylJeazjqbY%2BfAK3y926kji%2FdJn4y0hfLKsHFdn2%2BQj5fCFTxfG8TthfLuxnkTCGblxtAr31YTrJ5UuTXEbwCn%2FF5WNUgE7v3T1l7ZvDgiLCDaT6TDsb7LdEm%2Bi2Uiw3DAYSDLkKxP%2BRzPOMDlXZ73%2BTdZcQ75Ppt%2BlvpR47fRY%2BfXz%2FfJeNueC50CFu2vHTUNaU2ycppOC9EvYfEX5dQ9y%2BgZ9KK8qeX%2FLKIvyvSz5D88eqsS7wBR8xg1hUkQkwE%2F04MdXNU%2FqPwihSsQNW9cnH1ZRN45%2FLsnT7%2FZlw9K8urmw%2FpdQOJDhdcUyjBtlDvcYoZap%2BVXSpjucRyu3MSyH3v4sgE03WOZHi382qqmAO4xZZwLuC5Pczzrk5zHeRTPAMahXExcx6V8yDGMA7sQeKB9mx5OusSy%2BhOon%2BBvQixpnr79bPR6XrMPwy%2F4p84KcG3UJ65%2BRbno9zRoVo1YO1yZwpIxxaL%2BwceHFj6k2Y3Hz8w%2BCfgOuzJwRS%2FfT9fPq8vwP%2F0J');
        $bind = SAML2_Binding::getCurrentBinding();
        $this->assertInstanceOf('SAML2_HTTPRedirect', $bind);

        $_GET = array('SAMLart' => 'AAQAAI4sWYpfoDDYJrHzsMnG%2BjyNM94p5ejn49a%2BnZ0s3ylY7knQ6tkLMDE%3D');
        $bind = SAML2_Binding::getCurrentBinding();
        $this->assertInstanceOf('SAML2_HTTPArtifact', $bind);

        $_GET = array('aap' => 'noot');
        $this->setExpectedException('Exception', 'Unable to find the current binding.');
        $bind = SAML2_Binding::getCurrentBinding();
    }
    /**
     * Test binding guessing functionality.
     */
    public function testBindingGuesserPOST()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = array('SAMLRequest' => 'lZLBbtswDIZfxdDddpw0TiYkAZK4BQJ0RZGsO+wyCDKTCJMpT6S69u0nOejaXVr0JIDkT/L7xQWpzvZyHfiMe/gdgDh76iySHBJLETxKp8iQRNUBSdbysP56K8fFSPbesdPOijeS9xWKCDwbhyLbNUvxcz6bbLeT8WjeNLPpzea6qjbr2WZyVdXX9WZWVyL7Dp5i/VJEeRQRBdghsUKOoVE1zatxXtXfqlpOvsjp6IfImshgUPGgOjP3JMsS8GQQCo65goI/aofwxAXaUkV0QDZ6UJSm7UsyeLKQkzlhnlbdOiRIA99D05ciqYP38c1N11ujDYvsxnkNg8NLcVSWIHHcRyvMI/yLrF+cScNCB/4A/tFoeNjfvlLw+ZeJa7VFz/nVvAjsLzDWhTaxdGBtZOgd8R6oTxuJ1SJ9ixyc86tPduqAVatYLcq3TRaXo7mLHuyaexcxnxNkpz6wKEVMmx+HUsleIZloVYSPw/5sPSiOhrAPIMrVZeT/p7n6Cw==');
        $bind = SAML2_Binding::getCurrentBinding();
        $this->assertInstanceOf('SAML2_HTTPPost', $bind);

        $_POST = array('SAMLResponse' => 'PHNhbWxwOlJlc3BvbnNlIHhtbG5zOnNhbWxwPSJ1cm46b2FzaXM6bmFtZXM6dGM6U0FNTDoyLjA6cHJvdG9jb2wiIHhtbG5zOnNhbWw9InVybjpvYXNpczpuYW1lczp0YzpTQU1MOjIuMDphc3NlcnRpb24iIElEPSJDT1JUT2Y1MmFhMTA5NDQ3MWVkODJmYTRiM2QzZDBmNTcxNDE2MGNlMWEyMDciIFZlcnNpb249IjIuMCIgSXNzdWVJbnN0YW50PSIyMDE1LTEyLTE2VDE2OjM5OjU3WiIgRGVzdGluYXRpb249Imh0dHBzOi8vdGhraS1zaWQucHQtNDgudXRyLnN1cmZjbG91ZC5ubC9tZWxsb24vcG9zdFJlc3BvbnNlIiBJblJlc3BvbnNlVG89Il84NzNDQzMyMDhERDc1RkJFMTFCQTdCMzQxNkU2Qjc2MSI+PHNhbWw6SXNzdWVyPmh0dHBzOi8vZW5naW5lLnRlc3Quc3VyZmNvbmV4dC5ubC9hdXRoZW50aWNhdGlvbi9pZHAvbWV0YWRhdGE8L3NhbWw6SXNzdWVyPjxzYW1scDpTdGF0dXM+PHNhbWxwOlN0YXR1c0NvZGUgVmFsdWU9InVybjpvYXNpczpuYW1lczp0YzpTQU1MOjIuMDpzdGF0dXM6U3VjY2VzcyIvPjwvc2FtbHA6U3RhdHVzPjxzYW1sOkFzc2VydGlvbiB4bWxuczp4c2k9Imh0dHA6Ly93d3cudzMub3JnLzIwMDEvWE1MU2NoZW1hLWluc3RhbmNlIiB4bWxuczp4cz0iaHR0cDovL3d3dy53My5vcmcvMjAwMS9YTUxTY2hlbWEiIElEPSJDT1JUTzkxZDViNWI0OWJiOGVlMTY2YzlkNDFjNmEwMWE3Zjk2MzcwOTUyNTQiIFZlcnNpb249IjIuMCIgSXNzdWVJbnN0YW50PSIyMDE1LTEyLTE2VDE2OjM5OjU3WiI+PHNhbWw6SXNzdWVyPmh0dHBzOi8vZW5naW5lLnRlc3Quc3VyZmNvbmV4dC5ubC9hdXRoZW50aWNhdGlvbi9pZHAvbWV0YWRhdGE8L3NhbWw6SXNzdWVyPjxkczpTaWduYXR1cmUgeG1sbnM6ZHM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvMDkveG1sZHNpZyMiPgogIDxkczpTaWduZWRJbmZvPjxkczpDYW5vbmljYWxpemF0aW9uTWV0aG9kIEFsZ29yaXRobT0iaHR0cDovL3d3dy53My5vcmcvMjAwMS8xMC94bWwtZXhjLWMxNG4jIi8+CiAgICA8ZHM6U2lnbmF0dXJlTWV0aG9kIEFsZ29yaXRobT0iaHR0cDovL3d3dy53My5vcmcvMjAwMC8wOS94bWxkc2lnI3JzYS1zaGExIi8+CiAgPGRzOlJlZmVyZW5jZSBVUkk9IiNDT1JUTzkxZDViNWI0OWJiOGVlMTY2YzlkNDFjNmEwMWE3Zjk2MzcwOTUyNTQiPjxkczpUcmFuc2Zvcm1zPjxkczpUcmFuc2Zvcm0gQWxnb3JpdGhtPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwLzA5L3htbGRzaWcjZW52ZWxvcGVkLXNpZ25hdHVyZSIvPjxkczpUcmFuc2Zvcm0gQWxnb3JpdGhtPSJodHRwOi8vd3d3LnczLm9yZy8yMDAxLzEwL3htbC1leGMtYzE0biMiLz48L2RzOlRyYW5zZm9ybXM+PGRzOkRpZ2VzdE1ldGhvZCBBbGdvcml0aG09Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvMDkveG1sZHNpZyNzaGExIi8+PGRzOkRpZ2VzdFZhbHVlPmZGVzdzQW1KLzFNbTUxWWlIcUxTOE1QUHlsMD08L2RzOkRpZ2VzdFZhbHVlPjwvZHM6UmVmZXJlbmNlPjwvZHM6U2lnbmVkSW5mbz48ZHM6U2lnbmF0dXJlVmFsdWU+Wjh2WGQ2MTZaT0d0YmFIdytRWjJkUUVGYlVWdEg5Rmg5SmZHbFdLNzN3dHNmNVpjZGhsa2lJejA4UzE4K201TkZZS3pZaVpOeVZLWTRqZDZ2Q0gva3BIbVo1b3FXUzhmazhZU2dxYTZ6Q3UzTnVrN2NYZWZQQVB5QWtaUDZwb0pwcUM5QTc4T2c3Z3BzQ0VLaVE5WVhrMFpudFQ5TGdZSjg5R21HdnFialFVPTwvZHM6U2lnbmF0dXJlVmFsdWU+CjxkczpLZXlJbmZvPjxkczpYNTA5RGF0YT48ZHM6WDUwOUNlcnRpZmljYXRlPk1JSUMrekNDQW1TZ0F3SUJBZ0lKQVBKdkxqUXNSUjRpTUEwR0NTcUdTSWIzRFFFQkJRVUFNRjB4Q3pBSkJnTlZCQVlUQWs1TU1SQXdEZ1lEVlFRSUV3ZFZkSEpsWTJoME1SQXdEZ1lEVlFRSEV3ZFZkSEpsWTJoME1SQXdEZ1lEVlFRS0V3ZFRWVkpHYm1WME1SZ3dGZ1lEVlFRREV3OTBaWE4wTWlCellXMXNJR05sY25Rd0hoY05NVFV3TXpJME1UUXdNekUzV2hjTk1qVXdNekl4TVRRd016RTNXakJkTVFzd0NRWURWUVFHRXdKT1RERVFNQTRHQTFVRUNCTUhWWFJ5WldOb2RERVFNQTRHQTFVRUJ4TUhWWFJ5WldOb2RERVFNQTRHQTFVRUNoTUhVMVZTUm01bGRERVlNQllHQTFVRUF4TVBkR1Z6ZERJZ2MyRnRiQ0JqWlhKME1JR2ZNQTBHQ1NxR1NJYjNEUUVCQVFVQUE0R05BRENCaVFLQmdRQ3hLWXJuVzhOd3FkUndSd2FIdWx1ZUw2OWdRRlRKYmRmb0FTTGhZaWUyYk9pb0p5SncxZitjQXZwanNsNFNWWXB2RXNlSWV0WEg4TGdwazdLNzNQa0RKTDhkRmc0c0VqRkY2amdtanJPRVMzb3gvZ3RUZDlkL1Z3UEhJL3ZQSWNHeTFzYlZKNHBFTUZsNWQ4R09Bem9Ka05XZFBqOXdWNHJ2NHV2MzVNYXk4d0lEQVFBQm80SENNSUcvTUIwR0ExVWREZ1FXQkJUVElhUHdwS3BKbFk4ZUlNU3pOUlpValN0YmlqQ0Jqd1lEVlIwakJJR0hNSUdFZ0JUVElhUHdwS3BKbFk4ZUlNU3pOUlpValN0YmlxRmhwRjh3WFRFTE1Ba0dBMVVFQmhNQ1Rrd3hFREFPQmdOVkJBZ1RCMVYwY21WamFIUXhFREFPQmdOVkJBY1RCMVYwY21WamFIUXhFREFPQmdOVkJBb1RCMU5WVWtadVpYUXhHREFXQmdOVkJBTVREM1JsYzNReUlITmhiV3dnWTJWeWRJSUpBUEp2TGpRc1JSNGlNQXdHQTFVZEV3UUZNQU1CQWY4d0RRWUpLb1pJaHZjTkFRRUZCUUFEZ1lFQUs4RXZUVTBMZ0hKc1N1Z29yT2VtZ1JscHBNZkpBZU9tdXVaTmhTTVkyUWh1bUZPWnBhQWI4TkZJd1VLVVZ5eUpuU283azZrdEhDS0k5NHNRczk3NjI0MmhURERZRXdXSkQ5SGhBc0FxT28yMVVhOGdaVDM4L3dtNjJlM0tncktYdm5sakFiS1BYRFhKTTRha3o3eTZINnd2dklHVDZmMGYwaUpXSHEzNGp3dz08L2RzOlg1MDlDZXJ0aWZpY2F0ZT48L2RzOlg1MDlEYXRhPjwvZHM6S2V5SW5mbz48L2RzOlNpZ25hdHVyZT48c2FtbDpTdWJqZWN0PjxzYW1sOk5hbWVJRCBGb3JtYXQ9InVybjpvYXNpczpuYW1lczp0YzpTQU1MOjIuMDpuYW1laWQtZm9ybWF0OnRyYW5zaWVudCI+OGQ0NmIwM2VjNWZlMmZmNmJkNTNlOThhY2E3OTQ3ZjMzMDUzY2MwYjwvc2FtbDpOYW1lSUQ+PHNhbWw6U3ViamVjdENvbmZpcm1hdGlvbiBNZXRob2Q9InVybjpvYXNpczpuYW1lczp0YzpTQU1MOjIuMDpjbTpiZWFyZXIiPjxzYW1sOlN1YmplY3RDb25maXJtYXRpb25EYXRhIE5vdE9uT3JBZnRlcj0iMjAxNS0xMi0xNlQxNjo0NDo1N1oiIFJlY2lwaWVudD0iaHR0cHM6Ly90aGtpLXNpZC5wdC00OC51dHIuc3VyZmNsb3VkLm5sL21lbGxvbi9wb3N0UmVzcG9uc2UiIEluUmVzcG9uc2VUbz0iXzg3M0NDMzIwOERENzVGQkUxMUJBN0IzNDE2RTZCNzYxIi8+PC9zYW1sOlN1YmplY3RDb25maXJtYXRpb24+PC9zYW1sOlN1YmplY3Q+PHNhbWw6Q29uZGl0aW9ucyBOb3RCZWZvcmU9IjIwMTUtMTItMTZUMTY6Mzk6NTZaIiBOb3RPbk9yQWZ0ZXI9IjIwMTUtMTItMTZUMTY6NDQ6NTdaIj48c2FtbDpBdWRpZW5jZVJlc3RyaWN0aW9uPjxzYW1sOkF1ZGllbmNlPmh0dHBzOi8vdGhraS1zaWQucHQtNDgudXRyLnN1cmZjbG91ZC5ubC9tZWxsb24vbWV0YWRhdGE8L3NhbWw6QXVkaWVuY2U+PC9zYW1sOkF1ZGllbmNlUmVzdHJpY3Rpb24+PC9zYW1sOkNvbmRpdGlvbnM+PHNhbWw6QXV0aG5TdGF0ZW1lbnQgQXV0aG5JbnN0YW50PSIyMDE1LTEyLTE2VDE2OjM5OjU3WiIgU2Vzc2lvbk5vdE9uT3JBZnRlcj0iMjAxNS0xMi0xN1QwMDozOTo1N1oiIFNlc3Npb25JbmRleD0iXzYxMzU1ZTg2NTU3OGJhZDFlYTNkM2U1MzM2MDRhYjY3NjkyNThlOGI4YSI+PHNhbWw6QXV0aG5Db250ZXh0PjxzYW1sOkF1dGhuQ29udGV4dENsYXNzUmVmPnVybjpvYXNpczpuYW1lczp0YzpTQU1MOjIuMDphYzpjbGFzc2VzOlBhc3N3b3JkPC9zYW1sOkF1dGhuQ29udGV4dENsYXNzUmVmPjxzYW1sOkF1dGhlbnRpY2F0aW5nQXV0aG9yaXR5Pmh0dHBzOi8vb3BlbmlkcC5mZWlkZS5ubzwvc2FtbDpBdXRoZW50aWNhdGluZ0F1dGhvcml0eT48L3NhbWw6QXV0aG5Db250ZXh0Pjwvc2FtbDpBdXRoblN0YXRlbWVudD48L3NhbWw6QXNzZXJ0aW9uPjwvc2FtbHA6UmVzcG9uc2U+');
        $bind = SAML2_Binding::getCurrentBinding();
        $this->assertInstanceOf('SAML2_HTTPPost', $bind);

        $_POST = array();
        $_SERVER['CONTENT_TYPE'] = 'text/xml';
        $bind = SAML2_Binding::getCurrentBinding();
        $this->assertInstanceOf('SAML2_SOAP', $bind);

        $_POST = array('SAMLart' => 'AAQAAI4sWYpfoDDYJrHzsMnG+jyNM94p5ejn49a+nZ0s3ylY7knQ6tkLMDE=');
        $bind = SAML2_Binding::getCurrentBinding();
        $this->assertInstanceOf('SAML2_HTTPArtifact', $bind);

        $_POST=array('AAP' => 'Noot');
        unset($_SERVER['CONTENT_TYPE']);
        $this->setExpectedException('Exception', 'Unable to find the current binding.');
        $bind = SAML2_Binding::getCurrentBinding();
    }

    /**
     * Test binding guessing functionality with unsupported HTTP method.
     */
    public function testBindingGuesserWRONG()
    {
        $_SERVER['REQUEST_METHOD'] = 'PUT';
        $_SERVER['CONTENT_TYPE'] = 'text/xml';
        $this->setExpectedException('Exception', 'Unable to find the current binding.');
        $bind = SAML2_Binding::getCurrentBinding();
    }

    /**
     * test destination getter and setter.
     */
    public function testGetSetDestination()
    {
        $bind = SAML2_Binding::getBinding(SAML2_Const::BINDING_HTTP_POST);
        $dest = $bind->getDestination();
        $this->assertNull($dest);

        $bind->setDestination('http://example.org/example');
        $dest = $bind->getDestination();
        $this->assertEquals($dest, 'http://example.org/example');
    }
}
