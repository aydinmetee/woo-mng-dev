<?php
/**
 * Plugin Name: MNG Kargo Entegrasyonu
 * Plugin URI:  https://seninsiten.com
 * Description: WooCommerce siparişleri için MNG Kargo REST API entegrasyonu.
 * Version:     1.0.0
 * Author:      Mete Aydin
 * Author URI:  https://seninsiten.com
 * Text Domain: wc-mng-kargo
 */
if (!defined('ABSPATH')) {
    exit;
}

require_once plugin_dir_path(__FILE__) . 'includes/Model/MngOrderMapper.php';
require_once plugin_dir_path(__FILE__) . 'includes/Service/MngApiClient.php';
require_once plugin_dir_path(__FILE__) . 'includes/Settings/MngSettings.php';



use WcMngKargo\Model\MngOrderMapper;
use WcMngKargo\Service\MngApiClient;
use WcMngKargo\Settings\MngSettings;

class WcMngKargoPlugin
{

    public function __construct()
    {
        if (is_admin()) {
            new MngSettings();
        }
        add_action('before_woocommerce_init', function() {
            if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
                \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
            }
        });
        // 1. Sipariş detay sayfasına kutu ekle
        add_action('add_meta_boxes', [$this, 'addMetaBox']);

        // 2. AJAX isteğini dinle (Butona basılınca burası çalışacak)
        add_action('wp_ajax_mng_create_shipment', [$this, 'handleShipmentCreation']);
    }

    // Admin panelinde sağ tarafa kutu ekler
    public function addMetaBox()
    {
        // WooCommerce'in hangi ekranı kullandığını belirleyelim.
        // Eğer HPOS (High Performance Order Storage) aktifse ekran ID'si farklıdır.
        $screen = class_exists('\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController') &&
            wc_get_container()->get(\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class)->custom_orders_table_usage_is_enabled()
            ? wc_get_page_screen_id('shop-order')
            : 'shop_order';

        add_meta_box(
            'mng_kargo_box',           // ID
            'MNG Kargo Entegrasyonu',  // Başlık
            [$this, 'renderMetaBox'],  // Callback
            $screen,                   // Dinamik Ekran ID (Kritik Düzeltme)
            'side',                    // Konum
            'high'                     // Öncelik
        );
    }

    // Kutunun HTML çıktısı
    public function renderMetaBox($post)
    {
        // Siparişte daha önce alınmış bir barkod var mı?
        $existingBarcode = get_post_meta($post->ID, '_mng_tracking_number', true);

        if ($existingBarcode) {
            echo '<div style="color:green; font-weight:bold;">✅ Kargo Kodu: ' . esc_html($existingBarcode) . '</div>';
            echo '<p><small>Bu sipariş MNG\'ye iletilmiş.</small></p>';
        } else {
            // Buton ve Loading animasyonu için basit HTML/JS
            echo '<button type="button" id="mng-create-btn" class="button button-primary">MNG Kodu Oluştur</button>';
            echo '<div id="mng-result" style="margin-top:10px;"></div>';

            ?>
            <script type="text/javascript">
                jQuery(document).ready(function ($) {
                    $('#mng-create-btn').click(function () {
                        var btn = $(this);
                        btn.prop('disabled', true).text('İşleniyor...');
                        $('#mng-result').html('');

                        var data = {
                            'action': 'mng_create_shipment',
                            'order_id': <?php echo $post->ID; ?>
                        };

                        $.post(ajaxurl, data, function (response) {
                            if (response.success) {
                                $('#mng-result').html('<span style="color:green">' + response.data + '</span>');
                                setTimeout(function () { location.reload(); }, 1500); // Sayfayı yenile
                            } else {
                                $('#mng-result').html('<span style="color:red">Hata: ' + response.data + '</span>');
                                btn.prop('disabled', false).text('Tekrar Dene');
                            }
                        });
                    });
                });
            </script>
            <?php
        }
    }

    public function handleShipmentCreation()
    {
        // Güvenlik kontrolü (Admin mi?)
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

            // 3. API'ye gönder (Test için fake response dönebiliriz)
            // Şimdilik API credentials olmadığı için hata alacaksın, 
            // ama yapının çalıştığını görmek için burayı simüle edelim mi?

            // $barcode = $service->createShipment($payload); <--- GERÇEK KOD
            $barcode = "TEST-MNG-" . rand(1000, 9999); // <--- SİMÜLASYON (Test bitince sil)
            sleep(1); // API gecikmesi simülasyonu

            // 4. Barkodu kaydet
            update_post_meta($orderId, '_mng_tracking_number', $barcode);

            // 5. Sipariş notu ekle
            $order->add_order_note('MNG Kargo kodu oluşturuldu: ' . $barcode);
            $order->save();

            wp_send_json_success('Başarılı! Kod: ' . $barcode);

        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
}

// Plugin'i başlat
new WcMngKargoPlugin();