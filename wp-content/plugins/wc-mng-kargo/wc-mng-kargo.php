<?php
/**
 * Plugin Name: MNG Kargo Entegrasyonu
 * Plugin URI:  https://olderajewelry.com
 * Description: WooCommerce siparişleri için MNG Kargo REST API entegrasyonu For Askitos.
 * Version:     1.0.0
 * Author:      Metehan Aydın
 * Text Domain: wc-mng-kargo
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; 
}

// Gerekli dosyaları dahil et
require_once plugin_dir_path(__FILE__) . 'includes/Model/MngOrderMapper.php';
require_once plugin_dir_path(__FILE__) . 'includes/Service/MngApiClient.php';
require_once plugin_dir_path(__FILE__) . 'includes/Settings/MngSettings.php';

use WcMngKargo\Model\MngOrderMapper;
use WcMngKargo\Service\MngApiClient;
use WcMngKargo\Settings\MngSettings;

class WcMngKargoPlugin {

    public function __construct() {
        // HPOS (High Performance Order Storage) Uyumluluğu
        add_action('before_woocommerce_init', function() {
            if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
                \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
            }
        });

        // Admin Ayarlar Sayfası
        if (is_admin()) {
            new MngSettings();
        }

        // Meta Box ve AJAX İşlemleri
        add_action('add_meta_boxes', [$this, 'addMetaBox']);
        add_action('wp_ajax_mng_create_shipment', [$this, 'handleShipmentCreation']);
        add_action('wp_ajax_mng_delete_shipment', [$this, 'handleShipmentDeletion']);
    }

    // Sipariş detayına kutu ekle
    public function addMetaBox() {
        // HPOS ve Eski Sistem (Post) Kontrolü
        $screen = class_exists('\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController') && 
                  wc_get_container()->get(\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class)->custom_orders_table_usage_is_enabled()
                  ? wc_get_page_screen_id('shop-order') 
                  : 'shop_order';

        add_meta_box(
            'mng_kargo_box',           
            'MNG Kargo Entegrasyonu',  
            [$this, 'renderMetaBox'],  
            $screen,                   
            'side',                    
            'high'                     
        );
    }

    // Kutunun HTML Çıktısı
    public function renderMetaBox($post) {
        $orderId = $post instanceof WC_Order ? $post->get_id() : $post->ID;
        
        $existingBarcode = get_post_meta($orderId, '_mng_tracking_number', true);
        $existingRefId   = get_post_meta($orderId, '_mng_reference_id', true);

        echo '<div id="mng-wrapper">';

        if ($existingBarcode) {
            echo '<div style="margin-bottom: 10px; padding:10px; background:#e5faf2; border-left:4px solid #00a32a;">';
            echo '<div><strong>✅ Kargo Kodu (Fatura):</strong> ' . esc_html($existingBarcode) . '</div>';
            
            if ($existingRefId) {
                echo '<div style="margin-top:5px; font-size:12px; color:#555;"><strong>🔑 Ref ID (API için):</strong> ' . esc_html($existingRefId) . '</div>';
            }
            
            echo '</div>';
            echo '<button type="button" id="mng-reset-btn" class="button button-link-delete" style="text-decoration:none; color:#a00;">Bu Kaydı Sıfırla ve Tekrar Dene</button>';
        } else {
            echo '<button type="button" id="mng-create-btn" class="button button-primary">MNG Kodu Oluştur</button>';
        }

        echo '<div id="mng-result" style="margin-top:10px;"></div>';
        echo '</div>'; 
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            var orderId = <?php echo $orderId; ?>;

            // OLUŞTURMA
            $('#mng-create-btn').click(function() {
                var btn = $(this);
                btn.prop('disabled', true).text('İşleniyor...');
                $('#mng-result').html('');

                $.post(ajaxurl, {
                    'action': 'mng_create_shipment',
                    'order_id': orderId
                }, function(response) {
                    if(response.success) {
                        $('#mng-result').html('<span style="color:green; font-weight:bold;">' + response.data + '</span>');
                        setTimeout(function(){ location.reload(); }, 1500);
                    } else {
                        $('#mng-result').html('<span style="color:red; font-weight:bold;">' + response.data + '</span>');
                        btn.prop('disabled', false).text('Tekrar Dene');
                    }
                });
            });

            // SİLME (RESET)
            $('#mng-reset-btn').click(function() {
                if(!confirm('Bu MNG kaydını silip tekrar oluşturmak istiyor musunuz?')) return;
                var btn = $(this);
                btn.text('Siliniyor...');
                $.post(ajaxurl, {
                    'action': 'mng_delete_shipment',
                    'order_id': orderId
                }, function(response) {
                    if(response.success) {
                        location.reload(); 
                    } else {
                        alert('Hata: ' + response.data);
                    }
                });
            });
        });
        </script>
        <?php
    }

    // API İstek İşleyicisi
    public function handleShipmentCreation() {
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Yetkisiz işlem.');
        }

        $orderId = intval($_POST['order_id']);
        $order = wc_get_order($orderId);

        if (!$order) {
            wp_send_json_error('Sipariş bulunamadı.');
        }

        try {
            $payload = MngOrderMapper::mapOrderToMngPayload($order);
            $service = new MngApiClient();
            
            $result = $service->createShipment($payload);
            
            $barcode = $result['barcode'];
            $refId   = $result['referenceId'];

            update_post_meta($orderId, '_mng_tracking_number', $barcode);
            update_post_meta($orderId, '_mng_reference_id', $refId); // <--- Yeni Meta
            
            $order->add_order_note("MNG Kargo Başarılı.\nBarkod: $barcode\nRef ID: $refId");
            $order->save();

            wp_send_json_success('Başarılı! Ref: ' . $refId);

        } catch (Exception $e) {
            wp_send_json_error('Hata: ' . $e->getMessage());
        }
    }

    // Silme İşleyicisi
    public function handleShipmentDeletion() {
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Yetkisiz işlem.');
        }
        $orderId = intval($_POST['order_id']);
        $order = wc_get_order($orderId);

        if ($order) {
            $order->delete_meta_data('_mng_tracking_number');
            $order->delete_meta_data('_mng_reference_id');
            $order->add_order_note('MNG entegrasyon kaydı manuel sıfırlandı.');
            $order->save();
            wp_send_json_success('Silindi');
        } else {
            wp_send_json_error('Sipariş bulunamadı');
        }
    }
}

new WcMngKargoPlugin();