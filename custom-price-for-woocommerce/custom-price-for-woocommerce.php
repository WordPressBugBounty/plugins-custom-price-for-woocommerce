<?php
/**
 * Plugin Name: Custom Price for WooCommerce
 * Plugin URI: https://wpde.sk/custom-price-pl
 * Description: Allow customers to name product prices in WooCommerce. Receive donations and sell WooCommerce products at custom prices.
 * Version: 1.1.11
 * Author: WP Desk
 * Author URI: https://www.wpdesk.net/
 * Text Domain: custom-price-for-woocommerce
 * Domain Path: /lang/
 * Requires at least: 6.4
 * Tested up to: 6.7
 * WC requires at least: 9.1
 * WC tested up to: 9.5
 * Requires PHP: 7.4
 * Copyright 2020 WP Desk Ltd.
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @package Flexible Invoices
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly


/* THESE TWO VARIABLES CAN BE CHANGED AUTOMATICALLY */
$plugin_version = '1.1.11';
$plugin_release_timestamp = '2022-12-15 10:29';

$plugin_name        = 'Custom Price for WooCommerce';
$plugin_class_name  = '\WPDesk\WPDeskCPWFree\Plugin';
$plugin_text_domain = 'custom-price-for-woocommerce';
$product_id         = 'Custom Price for WooCommerce';
$plugin_file        = __FILE__;
$plugin_dir         = __DIR__;

$requirements = [
	'php'     => '7.4',
	'wp'      => '5.0',
	'plugins' => [
		[
			'name'      => 'woocommerce/woocommerce.php',
			'nice_name' => 'WooCommerce',
		],
	],
];

require __DIR__ . '/vendor_prefixed/wpdesk/wp-plugin-flow-common/src/plugin-init-php52-free.php';
