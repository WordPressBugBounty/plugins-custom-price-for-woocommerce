<?php

declare (strict_types=1);
namespace CPWFreeVendor\WPDesk\View\Resolver;

use CPWFreeVendor\WPDesk\View\Renderer\Renderer;
use CPWFreeVendor\WPDesk\View\Resolver\Exception\CanNotResolve;
/**
 * Locate templates, respecting WooCommerce template load order, prepending custom path to seek for templates. This supports user's template overrides by default.
 */
class WooTemplateResolver implements \CPWFreeVendor\WPDesk\View\Resolver\Resolver
{
    /** @var string */
    private $base_path;
    public function __construct(string $base_path)
    {
        if (!\function_exists('wc_locate_template')) {
            throw new \RuntimeException(\sprintf('The "%s" resolver needs the WooCommerce plugin. Make sure it is installed and active.', __CLASS__));
        }
        $this->base_path = $base_path;
    }
    public function resolve($name, \CPWFreeVendor\WPDesk\View\Renderer\Renderer $renderer = null) : string
    {
        $template = \wc_locate_template($name, '', $this->base_path);
        if ($template === '') {
            throw new \CPWFreeVendor\WPDesk\View\Resolver\Exception\CanNotResolve("Cannot resolve template {$name}");
        }
        return $template;
    }
}
