<?php

namespace CPWFreeVendor\WPDesk\Library\CustomPrice;

use CPWFreeVendor\Psr\Log\LoggerInterface;
use CPWFreeVendor\WPDesk\View\Renderer\Renderer;
use CPWFreeVendor\WPDesk\View\Resolver\DirResolver;
use CPWFreeVendor\WPDesk\View\Resolver\ChainResolver;
use CPWFreeVendor\WPDesk\PluginBuilder\Plugin\Hookable;
use CPWFreeVendor\WPDesk\Library\CustomPrice\Admin\Admin;
use CPWFreeVendor\WPDesk\View\Renderer\SimplePhpRenderer;
use CPWFreeVendor\WPDesk\PluginBuilder\Plugin\HookableParent;
use CPWFreeVendor\WPDesk\Library\CustomPrice\Admin\SettingsTab;
use CPWFreeVendor\WPDesk\Library\CustomPrice\Settings\SettingsForm;
use CPWFreeVendor\WPDesk\Library\CustomPrice\Admin\Product\ProductFields;
use CPWFreeVendor\WPDesk\Library\CustomPrice\Settings\SettingsIntegration;
use CPWFreeVendor\WPDesk\Library\CustomPrice\Admin\Product\SaveProductMeta;
use CPWFreeVendor\WPDesk\Library\CustomPrice\Compatibility\ExtensionSupport;
/**
 * Main class for integrate library with plugin.
 *
 * @package WPDesk\Library\CustomPrice
 */
class Integration implements Hookable
{
    use HookableParent;
    /**
     * @var Renderer
     */
    protected $renderer;
    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var bool
     */
    private static $is_super = \false;
    /**
     * @param bool $is_super
     */
    public function __construct(bool $is_super = \false)
    {
        self::$is_super = $is_super;
    }
    /**
     * @return bool
     */
    public static function is_super(): bool
    {
        return self::$is_super;
    }
    /**
     * @return string
     */
    final protected function get_library_url(): string
    {
        return trailingslashit(plugin_dir_url(__DIR__));
    }
    /**
     * @return string
     */
    final protected function get_library_path(): string
    {
        return trailingslashit(plugin_dir_path(__DIR__));
    }
    /**
     * @return Renderer
     */
    protected function get_renderer(): Renderer
    {
        $resolver = new ChainResolver();
        $resolver->appendResolver(new DirResolver($this->get_library_path() . '/templates'));
        return new SimplePhpRenderer($resolver);
    }
    /**
     * Fire hooks.
     */
    public function hooks()
    {
        $display = new Display($this->get_library_url(), $this->get_library_path());
        $cart = new Cart();
        $this->add_hookable($display);
        $this->add_hookable($cart);
        $this->add_hookable(new Order());
        $this->add_hookable(new ExtensionSupport($cart, $display));
        $this->add_hookable(new Admin($this->get_library_url(), $this->get_library_path()));
        $this->add_hookable(new ProductFields());
        $this->add_hookable(new SaveProductMeta());
        $this->add_hookable(new Settings\SettingsIntegration());
        $this->add_hookable(new Settings\Tabs\GeneralTab());
        $this->add_hookable(new Settings\Tabs\SupportTab($this->get_renderer()));
        $this->hooks_on_hookable_objects();
    }
}
