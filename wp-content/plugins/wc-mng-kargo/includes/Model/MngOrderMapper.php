<?php
namespace WcMngKargo\Model;

/**
 * WooCommerce Sipariş objesini MNG Standard Command API formatına çevirir.
 * İl/İlçe düzeltmeleri ve Loglama içerir.
 */
class MngOrderMapper {

    public static function mapOrderToMngPayload(\WC_Order $order): array {
        $orderId = (string) $order->get_id();
        
        $referenceId = 'OLD' . $orderId;

        $address = $order->get_shipping_address_1() . ' ' . $order->get_shipping_address_2();

        $isCod = ($order->get_payment_method() === 'cod') ? 1 : 0;
        $codAmount = $isCod ? (float) $order->get_total() : 0;
        $paymentType = 1; 


        $wcCity = $order->get_shipping_city();   
        $wcState = $order->get_shipping_state(); 
        $country = $order->get_shipping_country(); 

        $wcStateName = $wcState;
        if ($country && $wcState) {
            $states = WC()->countries->get_states($country);
            if (isset($states[$wcState])) {
                $wcStateName = $states[$wcState];
            }
        }

        $mngCityName = $wcStateName; 
        $mngDistrictName = $wcCity;
        
        $orderData = [
            'referenceId'         => $referenceId,
            'barcode'             => $referenceId, 
            'billOfLandingId'     => $orderId,     
            'isCOD'               => $isCod,       
            'codAmount'           => $codAmount,   
            'shipmentServiceType' => 1,
            'packagingType'       => 3,
            'content'             => 'Internet Siparis', 
            'smsPreference1'      => 1,            
            'smsPreference2'      => 0,            
            'smsPreference3'      => 0,            
            'paymentType'         => $paymentType,
            'deliveryType'        => 1,            
            'description'         => 'OLDERA Siparis ' . $orderId,
            'marketPlaceShortCode'=> '',
            'marketPlaceSaleCode' => '',
            'pudoId'              => ''
        ];

        $recipientData = [
            'customerId'        => "", 
            'refCustomerId'     => "",
            'fullName'          => $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name(),
            'address'           => mb_substr($address, 0, 250), 
            'mobilePhoneNumber' => $order->get_billing_phone(),
            'homePhoneNumber'   => "",
            'bussinessPhoneNumber' => "",
            'email'             => $order->get_billing_email(),
            'taxOffice'         => "",
            'taxNumber'         => "",
            'cityCode'          => 0, 
            'districtCode'      => 0,
            
            'cityName'          => $mngCityName,     
            'districtName'      => $mngDistrictName 
        ];

        $pieceList = [];
        $pieceList[] = [
            'barcode' => $referenceId . '-1', 
            'desi'    => 1,
            'kg'      => 1,
            'content' => 'Urun Paketi'
        ];

        $payload = [
            'order'          => $orderData,
            'recipient'      => $recipientData,
            'orderPieceList' => $pieceList
        ];

        self::writeDebugLog($order, $payload);

        return $payload;
    }

    private static function writeDebugLog($order, $payload) {
        $logFile = plugin_dir_path(__DIR__) . '../mng_debug_log.txt';
        
        $logData  = "========================================\n";
        $logData .= "Tarih: " . date('d-m-Y H:i:s') . "\n";
        $logData .= "Sipariş ID: " . $order->get_id() . "\n";
        $logData .= "========================================\n\n";

        $logData .= "--- [1] WOOCOMMERCE HAM SİPARİŞ VERİSİ ---\n";
        $logData .= print_r($order->get_data(), true); 
        $logData .= "\n\n";

        $logData .= "--- [2] MNG REQUEST PAYLOAD (GİDEN VERİ) ---\n";
        $logData .= json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $logData .= "\n\n";
        
        $logData .= "----------------------------------------\n\n";

        file_put_contents($logFile, $logData, FILE_APPEND);
    }
}