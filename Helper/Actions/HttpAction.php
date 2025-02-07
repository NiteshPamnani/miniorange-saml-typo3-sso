<?php

namespace Miniorange\Helper\Actions;

use Miniorange\Helper\Constants;
use Miniorange\Helper\lib\XMLSecLibs\XMLSecurityKey;
use Miniorange\Helper\SAMLUtilities;
use Miniorange\Helper\Utilities;

class HttpAction
{
    /**
     * @param $samlResponse
     * @param $sendRelayState
     * @param $ssoUrl
     */
    protected function sendHTTPRedirectResponse($samlResponse,$sendRelayState,$ssoUrl)
    {
        $redirect = $ssoUrl;
        $redirect .= strpos($ssoUrl,'?') !== false  ? '&' : '?';
        $redirect .= 'SAMLResponse=' . $samlResponse . '&RelayState=' . urlencode($sendRelayState);
        header('Location: '.$redirect);
        exit();
    }

    /**
     * This function is used to send LogoutRequest & AuthRequest as a request Parameter.
     * LogoutRequest & AuthRequest is sent in the request parameter if the binding is
     * set as HTTP Redirect. Http Redirect is the default way Authn Request
     * is sent.
     *
     * TODO : Function also generates the signature and appends it in the parameter as
     * TODO : well along with the relayState parameter
     * @param $samlRequest
     * @param $sendRelayState
     * @param $idpUrl
     * @throws \Exception
     */
    public function sendHTTPRedirectRequest($samlRequest,$sendRelayState,$idpUrl)
    {
        $samlRequest = "SAMLRequest=" . $samlRequest . "&RelayState=" . urlencode($sendRelayState)
                            . '&SigAlg='. urlencode(XMLSecurityKey::RSA_SHA256);
        $param = array( 'type' => 'private');
        $key = new XMLSecurityKey(XMLSecurityKey::RSA_SHA256, $param);
        $certFilePath = file_get_contents(Utilities::getResourceDir() . DIRECTORY_SEPARATOR . 'sp-key.key');
        $key->loadKey($certFilePath);
        $signature = $key->signData($samlRequest);
        $signature = base64_encode($signature);
        $redirect = $idpUrl;
        $redirect .= strpos($idpUrl,'?') !== false  ? '&' : '?';
        $redirect .= $samlRequest . '&Signature=' . urlencode($signature);
        header('Location: '.$redirect);
        exit();
    }


    /**
     * This function is used to send LogoutRequest & AuthRequest as a post Parameter.
     * LogoutRequest & AuthRequest is sent in the post parameter if the binding is
     * set as HTTP Post.
     *
     * TODO : Function also generates the signature and appends it in the XML document
     * TODO : before sending it over as post
     * TODO : parameter data along with the relayState parameter.
     * @param $samlRequest
     * @param $sendRelayState
     * @param $idpUrl
     */
    public function sendHTTPPostRequest($samlRequest,$sendRelayState,$sloUrl)
    {
        $privateKeyPath = Utilities::getResourceDir() . DIRECTORY_SEPARATOR . Constants::SP_KEY;
        $publicCertPath =  Utilities::getResourceDir() . DIRECTORY_SEPARATOR . Constants::SP_CERT;
        $signedXML = SAMLUtilities::signXML($samlRequest, file_get_contents($publicCertPath),
            file_get_contents($privateKeyPath), 'NameIDPolicy' );
        $base64EncodedXML = base64_encode($signedXML);
        //post request
        ob_clean();
        printf("  <html>
                    <head>
                    <script type=\"text/javascript\">
                            $(function(){
                                document.getElementById('saml-request-form').submit(); 
                            });
                    </script>
                    </head>
                    <body>
                        Please wait...
                        <form action=\"%s\" method=\"post\" id=\"saml-request-form\" style=\"display:none;\">
                            <input type=\"hidden\" name=\"SAMLRequest\" value=\"%s\" />
                            <input type=\"hidden\" name=\"RelayState\" value=\"%s\" />
                        </form>
                    </body>
                </html>", $sloUrl,$base64EncodedXML,htmlentities($sendRelayState));
    }


    /**
     * This function is used to send Logout Response as a post Parameter.
     * Logout Response is sent in the post parameter if the binding is
     * set as HTTP Post.
     *
     * TODO : Function also generates the signature and appends it in the XML document
     * TODO : before sending it over as post
     * TODO : parameter data along with the relayState parameter.
     *
     * @param $samlResponse
     * @param $sendRelayState
     * @param $ssoUrl
     */
    public function sendHTTPPostResponse($samlResponse,$sendRelayState,$ssoUrl)
    {
        $privateKeyPath = Utilities::getResourceDir() . DIRECTORY_SEPARATOR . 'sp-key.key';
        $publicCertPath =  Utilities::getResourceDir() . DIRECTORY_SEPARATOR . 'sp-certificate.crt';
        $signedXML = SAMLUtilities::signXML($samlResponse, file_get_contents($publicCertPath),
            file_get_contents($privateKeyPath), 'NameID' );
        $base64EncodedXML = base64_encode($signedXML);
        //post request
        ob_clean();
        printf("  <html><head><script src='https://code.jquery.com/jquery-1.12.4.min.js'></script><script type=\"text/javascript\">
                    $(function(){document.forms['saml-request-form'].submit();});</script></head>
                    <body>
                        Please wait...
                        <form action=\"%s\" method=\"post\" id=\"saml-request-form\" style=\"display:none;\">
                            <input type=\"hidden\" name=\"SAMLResponse\" value=\"%s\" />
                            <input type=\"hidden\" name=\"RelayState\" value=\"%s\" />
                        </form>
                    </body>
                </html>",$ssoUrl,$base64EncodedXML,htmlentities($sendRelayState));
    }
}
