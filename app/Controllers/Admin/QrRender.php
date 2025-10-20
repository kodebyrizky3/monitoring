<?php
namespace App\Controllers\Admin;

use App\Controllers\BaseController;

class QrRender extends BaseController
{
    // GET /admin/qr/png/{token}
    public function show(string $token)
    {
        $token = trim($token);
        if ($token === '') {
            return $this->response->setStatusCode(400)->setBody('Bad token');
        }

        $dataUrl = site_url('ac/'.rawurlencode($token));
        $api = 'https://api.qrserver.com/v1/create-qr-code/?size=320x320&qzone=2&data='.rawurlencode($dataUrl);
        $png = $this->httpGet($api);

        if ($png === false) {
            return $this->response->setStatusCode(502)->setBody('QR service error');
        }

        return $this->response->setHeader('Content-Type','image/png')->setBody($png);
    }

    private function httpGet(string $url)
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT        => 10,
                CURLOPT_SSL_VERIFYPEER => true,
            ]);
            $out = curl_exec($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($code === 200 && $out !== false) return $out;
        }
        return @file_get_contents($url);
    }
}