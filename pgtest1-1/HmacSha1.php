<?php

require_once dirname(__FILE__).'/SignatureMethod.php';

class HmacSha1 extends SignatureMethod
{
    public function getName()
    {
        return "HMAC-SHA1";
    }

    public function buildSignature(Request $request, Consumer $consumer, Token $token = null)
    {
        $signatureBase = $request->getSignatureBaseString();
        $parts = [$consumer->secret, null !== $token ? $token->secret : ""];
        $parts = Util::urlencodeRfc3986($parts);
        $key = implode('&', $parts);
        return base64_encode(hash_hmac('sha1', $signatureBase, $key, true));
    }
}