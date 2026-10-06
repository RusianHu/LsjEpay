<?php

class PayPalWebhookCertificate
{
    public static function isTrustedUrl($url)
    {
        if (!is_string($url) || strlen($url) > 2048) {
            return false;
        }
        $parts = parse_url($url);
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'], $parts['path'])
            || strtolower($parts['scheme']) !== 'https') {
            return false;
        }
        $hosts = ['api.paypal.com', 'api.sandbox.paypal.com',
            'api-m.paypal.com', 'api-m.sandbox.paypal.com'];
        if (!in_array(strtolower($parts['host']), $hosts, true)
            || (isset($parts['port']) && $parts['port'] !== 443)) {
            return false;
        }
        foreach (['user', 'pass', 'query', 'fragment'] as $component) {
            if (isset($parts[$component])) {
                return false;
            }
        }
        return preg_match('~^/v1/notifications/certs/CERT-[A-Za-z0-9_-]+$~D', $parts['path']) === 1;
    }

    public static function download($url)
    {
        if (!self::isTrustedUrl($url)) {
            throw new Exception('PayPal证书地址不可信');
        }
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 10,
        ]);
        if (PHP_OS_FAMILY === 'Windows' && defined('CURLSSLOPT_NATIVE_CA')) {
            curl_setopt($curl, CURLOPT_SSL_OPTIONS, CURLSSLOPT_NATIVE_CA);
        }
        if (defined('CURLOPT_PROTOCOLS_STR')) {
            curl_setopt($curl, CURLOPT_PROTOCOLS_STR, 'https');
        } else {
            curl_setopt($curl, CURLOPT_PROTOCOLS, CURLPROTO_HTTPS);
        }
        $certificate = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        if ($status !== 200 || !is_string($certificate)
            || $certificate === '' || strlen($certificate) > 16384) {
            throw new Exception('PayPal证书下载失败');
        }
        return $certificate;
    }
}
