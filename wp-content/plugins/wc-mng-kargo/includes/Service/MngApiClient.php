<?php
namespace WcMngKargo\Service;

use Exception;

class MngApiClient {
    
    private const TEST_HOST = 'https://testapi.mngkargo.com.tr';
    private const PROD_HOST = 'https://api.mngkargo.com.tr';
    
    private const API_PATH  = '/mngapi/api';

    private string $baseUrl; 
    private string $username;     
    private string $password;     
    private string $clientId;     
    private string $clientSecret; 

    public function __construct() {
        $options = get_option('mng_kargo_option_name');

        $this->username     = $options['username'] ?? '';
        $this->password     = $options['password'] ?? '';
        $this->clientId     = $options['client_id'] ?? '';
        $this->clientSecret = $options['client_secret'] ?? '';

        $environment = $options['environment'] ?? 'test'; 

        if ($environment === 'production') {
            $this->baseUrl = self::PROD_HOST . self::API_PATH;
        } else {
            $this->baseUrl = self::TEST_HOST . self::API_PATH;
        }

        if (empty($this->username) || empty($this->clientId)) {
            throw new Exception('API ayarları eksik! Lütfen MNG Kargo Ayarlarını kontrol edin.');
        }
    }

    /**
     * Siparişi MNG Kargo'ya iletir.
     * Dinamik URL yapısını kullanır.
     */
    public function createShipment(array $payload): array {
        $jwtToken = $this->loginAndGetToken();

        $url = $this->baseUrl . '/standardcmdapi/createOrder';

        $headers = [
            'Authorization'       => 'Bearer ' . $jwtToken,
            'Content-Type'        => 'application/json',
            'X-IBM-Client-Id'     => $this->clientId,
            'X-IBM-Client-Secret' => $this->clientSecret,
            'x-api-version'       => '1.0'
        ];
        
        $response = wp_remote_post($url, [
            'headers' => $headers,
            'body'    => json_encode($payload),
            'timeout' => 45
        ]);

        if (is_wp_error($response)) {
            throw new Exception('MNG Sipariş Servisi Hatası: ' . $response->get_error_message());
        }

        $httpCode = wp_remote_retrieve_response_code($response);
        $body     = wp_remote_retrieve_body($response);
        $data     = json_decode($body, true);

        if (is_array($data) && isset($data[0]) && is_array($data[0])) {
            $data = $data[0];
        }

        if ($httpCode === 200) {
            return [
                'barcode'     => $data['orderInvoiceId'] ?? $data['referenceId'] ?? 'YOK',
                'referenceId' => $data['referenceId'] ?? 'YOK'
            ];
        }

        $errorMsg = 'API Hatası';
        if (isset($data['message'])) {
            $errorMsg = $data['message'];
        } elseif (isset($data['detail'])) {
            $errorMsg = $data['detail'];
        }
        
        throw new Exception("MNG Sipariş Oluşturulamadı ($httpCode): $errorMsg");
    }

    /**
     * Token Alma (Identity API)
     */
    private function loginAndGetToken(): string {
        $url = $this->baseUrl . '/token';

        $body = [
            'customerNumber' => $this->username,
            'password'       => $this->password,
            'identityType'   => 1 
        ];

        $headers = [
            'Content-Type'        => 'application/json',
            'X-IBM-Client-Id'     => $this->clientId,
            'X-IBM-Client-Secret' => $this->clientSecret,
            'x-api-version'       => '1.0'
        ];

        $response = wp_remote_post($url, [
            'headers' => $headers,
            'body'    => json_encode($body),
            'timeout' => 30
        ]);

        if (is_wp_error($response)) {
            throw new Exception('Token Hatası: ' . $response->get_error_message());
        }

        $bodyResponse = wp_remote_retrieve_body($response);
        $data = json_decode($bodyResponse, true);

        if (empty($data['jwt'])) {
            $msg = $data['message'] ?? $data['detail'] ?? 'Bilinmeyen Hata';
            throw new Exception('JWT Token alınamadı: ' . $msg);
        }

        return $data['jwt'];
    }
}