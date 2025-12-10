<?php
namespace WcMngKargo\Settings;

class MngSettings {

    private $options;

    public function __construct() {
        add_action('admin_menu', [$this, 'addPluginPage']);
        add_action('admin_init', [$this, 'pageInit']);
    }

    public function addPluginPage() {
        add_submenu_page(
            'woocommerce',
            'MNG Kargo Ayarları',
            'MNG Kargo Ayar',
            'manage_options',
            'mng-kargo-settings',
            [$this, 'createAdminPage']
        );
    }

    public function createAdminPage() {
        $this->options = get_option('mng_kargo_option_name');
        ?>
        <div class="wrap">
            <h1>MNG Kargo API Ayarları</h1>
            <p>MNG Kargo entegrasyonu için gerekli kimlik bilgilerini aşağıya giriniz.</p>
            <form method="post" action="options.php">
                <?php
                settings_fields('mng_kargo_option_group');
                do_settings_sections('mng-kargo-settings-admin');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public function pageInit() {
        register_setting(
            'mng_kargo_option_group', 
            'mng_kargo_option_name', 
            [$this, 'sanitize']
        );

        add_settings_section(
            'setting_section_id', 
            'API Kimlik Bilgileri', 
            null, 
            'mng-kargo-settings-admin'
        );

        // API URL alanını kaldırdık. 
        // Sadece Kullanıcı Adı, Şifre, Client ID ve Secret kaldı.

        add_settings_field(
            'username', 
            'Kullanıcı Adı (Müşteri No)', 
            [$this, 'usernameCallback'], 
            'mng-kargo-settings-admin', 
            'setting_section_id'
        );

        add_settings_field(
            'password', 
            'Şifre', 
            [$this, 'passwordCallback'], 
            'mng-kargo-settings-admin', 
            'setting_section_id'
        );

        add_settings_field(
            'client_id', 
            'IBM Client ID', 
            [$this, 'clientIdCallback'], 
            'mng-kargo-settings-admin', 
            'setting_section_id'
        );

        add_settings_field(
            'client_secret', 
            'IBM Client Secret', 
            [$this, 'clientSecretCallback'], 
            'mng-kargo-settings-admin', 
            'setting_section_id'
        );
    }

    public function sanitize($input) {
        $new_input = array();
        
        // URL sanitize işlemini de kaldırdık
        if(isset($input['username']))
            $new_input['username'] = sanitize_text_field($input['username']);
        if(isset($input['password']))
            $new_input['password'] = sanitize_text_field($input['password']);
        if(isset($input['client_id']))
            $new_input['client_id'] = sanitize_text_field($input['client_id']);
        if(isset($input['client_secret']))
            $new_input['client_secret'] = sanitize_text_field($input['client_secret']);

        return $new_input;
    }

    // URL Callback fonksiyonunu sildik.

    public function usernameCallback() {
        printf(
            '<input type="text" name="mng_kargo_option_name[username]" value="%s" style="width: 300px;" placeholder="Örn: 12345678" />',
            isset($this->options['username']) ? esc_attr($this->options['username']) : ''
        );
    }

    public function passwordCallback() {
        printf(
            '<input type="password" name="mng_kargo_option_name[password]" value="%s" style="width: 300px;" />',
            isset($this->options['password']) ? esc_attr($this->options['password']) : ''
        );
    }

    public function clientIdCallback() {
        printf(
            '<input type="text" name="mng_kargo_option_name[client_id]" value="%s" style="width: 400px;" placeholder="X-IBM-Client-Id değerini giriniz" />',
            isset($this->options['client_id']) ? esc_attr($this->options['client_id']) : ''
        );
    }

    public function clientSecretCallback() {
        printf(
            '<input type="password" name="mng_kargo_option_name[client_secret]" value="%s" style="width: 400px;" placeholder="X-IBM-Client-Secret değerini giriniz" />',
            isset($this->options['client_secret']) ? esc_attr($this->options['client_secret']) : ''
        );
    }
}