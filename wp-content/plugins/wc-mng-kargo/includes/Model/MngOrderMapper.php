<?php
namespace WcMngKargo\Model;

/**
 * WooCommerce Sipariş objesini MNG Standard Command API formatına çevirir.
 * JSON Şeması Referansı: Standard_Command_API-1.0.json
 */
class MngOrderMapper {

    public static function mapOrderToMngPayload(\WC_Order $order): array {
        $orderId = (string) $order->get_id();
        
        // KRİTİK: ReferenceId ve Barkod BÜYÜK HARF olmalı
        // WC-{SiparişID} formatı unique olması için idealdir.
        $referenceId = strtoupper('WC-' . $orderId); 

        // Adres Satırlarını Birleştir
        $address = $order->get_shipping_address_1() . ' ' . $order->get_shipping_address_2();

        // Kapıda Ödeme (COD) Kontrolü
        // WooCommerce'de ödeme yöntemi 'cod' ise işaretle
        $isCod = ($order->get_payment_method() === 'cod') ? 1 : 0;
        $codAmount = $isCod ? (float) $order->get_total() : 0;

        // Ödeme Tipi (PaymentType)
        // 1: GONDERICI_ODER (E-ticarette kargo dahil satışlar için standart)
        // 2: ALICI_ODER (Kargo ücretini müşteri kapıda ödeyecekse)
        // Şimdilik varsayılan olarak gönderici öder (1) yapıyoruz.
        $paymentType = 1; 

        // --- 1. Order Objesi ---
        $orderData = [
            'referenceId'         => $referenceId,
            'barcode'             => $referenceId, // Ref ID ile aynı olabilir
            'billOfLandingId'     => $orderId,     // İrsaliye No (Sipariş No yaptık)
            'isCOD'               => $isCod,       // 0 veya 1
            'codAmount'           => $codAmount,   // Tutar
            'shipmentServiceType' => 1,            // 1: Standart Teslimat
            'packagingType'       => 3,            // 3: Paket (Koli için 4 seçilebilir)
            'content'             => 'Internet Siparis', // Genel içerik
            'smsPreference1'      => 1,            // Varış şubesi SMS
            'smsPreference2'      => 0,            // Hazırlanma SMS (Maliyet yaratabilir, kapalı)
            'smsPreference3'      => 1,            // Teslim SMS
            'paymentType'         => $paymentType,
            'deliveryType'        => 1,            // 1: Adrese Teslim
            'description'         => 'Siparis #' . $orderId,
            'marketPlaceShortCode'=> '',
            'marketPlaceSaleCode' => '',
            'pudoId'              => ''
        ];

        // --- 2. Recipient (Alıcı) Objesi ---
        $recipientData = [
            'customerId'        => "", // Bireysel gönderimlerde boş bırakılır veya 0
            'refCustomerId'     => "",
            'fullName'          => $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name(),
            'address'           => mb_substr($address, 0, 250), // Çok uzun adresleri kırp
            'mobilePhoneNumber' => $order->get_billing_phone(),
            'homePhoneNumber'   => "",
            'bussinessPhoneNumber' => "",
            'email'             => $order->get_billing_email(),
            'taxOffice'         => "",
            'taxNumber'         => "",
            
            // İl/İlçe Eşleşmesi: Kodları bilmiyorsak 0 gönderip isimleri doldururuz.
            'cityCode'          => 0, 
            'districtCode'      => 0,
            'cityName'          => $order->get_shipping_city(),
            'districtName'      => $order->get_shipping_state() // WC'de İlçe bilgisi genelde State alanındadır
        ];

        // --- 3. OrderPieceList (Parça) Listesi ---
        // Varsayılan 1 parça, 1 Desi, 1 KG gönderiyoruz.
        // İleride ürün özelliklerinden çekilecek şekilde geliştirilebilir.
        $pieceList = [];
        $pieceList[] = [
            'barcode' => $referenceId . '-1', // Parça barkodu da unique olmalı
            'desi'    => 1,
            'kg'      => 1,
            'content' => 'Urun Paketi'
        ];

        // MNG API'nin beklediği Kök Yapı
        return [
            'order'          => $orderData,
            'recipient'      => $recipientData,
            'orderPieceList' => $pieceList
        ];
    }
}