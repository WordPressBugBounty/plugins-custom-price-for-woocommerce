<?php

namespace CPWFreeVendor\WPDesk\Library\CustomPrice\Settings\Tabs;

use CPWFreeVendor\WPDesk\PluginBuilder\Plugin\Hookable;
abstract class BaseTab implements Hookable
{
    /**
     * @var string
     */
    protected $tab_id;
    public function hooks()
    {
        add_filter('woocommerce_get_sections_custom_price', [$this, 'register_section_name']);
    }
    /**
     * @param array<string, string> $sections already registered sections
     *
     * @return array<string, string> updated sections
     */
    public function register_section_name($sections)
    {
        $sections[$this->tab_id] = $this->get_tab_label();
        return $sections;
    }
    /**
     * @return string
     */
    abstract public function get_tab_label();
}
