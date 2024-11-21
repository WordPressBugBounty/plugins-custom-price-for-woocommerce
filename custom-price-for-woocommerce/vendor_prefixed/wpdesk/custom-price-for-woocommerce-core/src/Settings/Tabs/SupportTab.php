<?php

namespace CPWFreeVendor\WPDesk\Library\CustomPrice\Settings\Tabs;

use CPWFreeVendor\WPDesk\View\Renderer\Renderer;
use CPWFreeVendor\WPDesk\PluginBuilder\Plugin\Hookable;
use CPWFreeVendor\WPDesk\Library\Marketing\Boxes\Assets;
use CPWFreeVendor\WPDesk\Library\CustomPrice\Integration;
use CPWFreeVendor\WPDesk\Library\Marketing\Boxes\MarketingBoxes;
use CPWFreeVendor\WPDesk\Library\CustomPrice\Settings\SettingsPage;
class SupportTab extends BaseTab implements Hookable
{
    /**
     * @var Renderer
     */
    protected $renderer;
    public function __construct(Renderer $renderer)
    {
        $this->renderer = $renderer;
        $this->tab_id = 'support';
    }
    public function hooks()
    {
        parent::hooks();
        \add_action('woocommerce_admin_field_cp_support_settings', [$this, 'cp_support_settings']);
        \add_filter('woocommerce_get_settings_custom_price', [$this, 'add_fields'], 10, 2);
        \add_action('admin_enqueue_scripts', [$this, 'load_assets_for_marketing_page']);
    }
    public function get_tab_label()
    {
        return __('Start Here', 'custom-price-for-woocommerce');
    }
    /**
     * Get settings array
     *
     * @param array<int, array<string, string>> $settings
     * @param string $current_section
     *
     * @return array<int, array<string, string>>
     */
    public function add_fields($settings, $current_section)
    {
        if ($current_section !== $this->tab_id) {
            return $settings;
        }
        return [['type' => 'cp_support_settings']];
    }
    /**
     * @return void
     */
    public function cp_support_settings()
    {
        $local = get_locale();
        if ($local === 'en_US') {
            $local = 'en';
        }
        $slug = Integration::is_super() ? 'custom-price-for-woocommerce-pro' : 'custom-price-for-woocommerce';
        $boxes = new MarketingBoxes($slug, $local);
        $this->renderer->output_render('marketing-page', ['boxes' => $boxes]);
    }
    /**
     * Loads the assets for the marketing page.
     * wp-admin/admin.php?page=wc-settings&tab=custom_price&section=support
     *
     * @return void
     */
    public function load_assets_for_marketing_page(): void
    {
        if (!isset($_GET['page']) || !isset($_GET['tab']) || !isset($_GET['section']) || 'wc-settings' !== $_GET['page'] || 'custom_price' !== $_GET['tab'] || 'support' !== $_GET['section']) {
            return;
        }
        Assets::enqueue_assets();
        Assets::enqueue_owl_assets();
    }
}
