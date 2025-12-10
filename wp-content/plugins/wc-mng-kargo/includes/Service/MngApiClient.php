<?php
namespace WcMngKargo\Service;

use Exception;

class MngApiClient {
    
    // Identity API Base URL (Token için)
    //private const AUTH_URL = 'https://api.mngkargo.com.tr/mngapi/api'; 
    private const AUTH_URL = 'https://testapi.mngkargo.com.tr/mngapi/api'; 
    
    // Standard Command API Base URL (Sipariş için)
    //private const CMD_URL = 'https://api.mngkargo.com.tr/mngapi/api/standardcmdapi';
    private const CMD_URL = 'https://testapi.mngkargo.com.tr/mngapi/api/standardcmdapi';

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

        if (empty($this->username) || empty($this->clientId)) {
            throw new Exception('API ayarları eksik! Lütfen MNG Kargo Ayarlarını kontrol edin.');
        }
    }

    /**
     * Siparişi MNG Kargo'ya iletir.
     * Endpoint: /createOrder
     */
    public function createShipment(array $payload): string {
        // 1. Token Al
        $jwtToken = $this->loginAndGetToken();

        // 2. Siparişi Oluştur
        $url = self::CMD_URL . '/createOrder';

        $headers = [
            'Authorization'       => 'Bearer ' . $jwtToken,
            'Content-Type'        => 'application/json',
            'X-IBM-Client-Id'     => $this->clientId,    // Header bilgisi
            'X-IBM-Client-Secret' => $this->clientSecret,
            'x-api-version'       => '1.0'
        ];
        
        $response = wp_remote_post($url, [
            'headers' => $headers,
            'body'    => json_encode($payload), // Mapper'dan gelen yapıyı direkt gönderiyoruz
            'timeout' => 45
        ]);

        if (is_wp_error($response)) {
            throw new Exception('MNG Sipariş Servisi Hatası: ' . $response->get_error_message());
        }

        $httpCode = wp_remote_retrieve_response_code($response);
        $body     = wp_remote_retrieve_body($response);
        $data     = json_decode($body, true);

        // Başarılı işlem (200 OK)
        if ($httpCode === 200) {
            // Yanıt şemasında orderInvoiceId veya referenceId döner
            if (!empty($data['orderInvoiceId'])) {
                 return $data['orderInvoiceId'];
            } elseif (!empty($data['referenceId'])) {
                 return $data['referenceId'];
            }
        }

        // Hata Durumu Analizi
        $errorMsg = 'API Hatası';
        if (isset($data['message'])) {
            $errorMsg = $data['message'];
        } elseif (isset($data['detail'])) {
            $errorMsg = $data['detail'];
        }
        
        // MNG bazen 400 döner ve hatayı body içinde verir
        throw new Exception("MNG Sipariş Oluşturulamadı ($httpCode): $errorMsg");
    }

    /**
     * Token Alma (Identity API)
     */
    private function loginAndGetToken(): string {
        $url = self::AUTH_URL . '/token';

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
            throw new Exception('JWT Token alınamadı. Kullanıcı bilgilerinizi kontrol edin.');
        }

        return $data['jwt'];
    }
}