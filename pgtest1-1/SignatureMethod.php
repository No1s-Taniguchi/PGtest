<?php

abstract class SignatureMethod
{
    abstract public function getName();
    abstract public function buildSignature(Request $request, Consumer $consumer, Token $token = null);

    public function checkSignature(Request $request, Consumer $consumer, Token $token, $signature)
    {
        $built = $this->buildSignature($request, $consumer, $token);
        // Check for zero length, although unlikely here
        if (strlen($built) == 0 || strlen($signature) == 0) {
            return false;
        }
        if (strlen($built) != strlen($signature)) {
            return false;
        }
        // Avoid a timing leak with a (hopefully) time insensitive compare
        $result = 0;
        for ($i = 0; $i < strlen($signature); $i++) {
            $result |= ord($built{$i}) ^ ord($signature{$i});
        }
        return $result == 0;
    }
}