<?php

namespace CPWFreeVendor\WPDesk\Library\CustomPrice;

use CPWFreeVendor\WPDesk\PluginBuilder\Plugin\Hookable;
use WC_Product;
use Exception;
use WC_Subscriptions_Product;
class Cart implements Hookable
{
    /**
     * Holds the custom prices for products in the cart.
     *
     * @var array<int, string>
     */
    private array $cpw_prices = [];
    private array $price_filters = ['woocommerce_product_get_price', 'woocommerce_product_get_regular_price', 'woocommerce_product_get_sale_price', 'woocommerce_product_variation_get_price', 'woocommerce_product_variation_get_regular_price', 'woocommerce_product_variation_get_sale_price'];
    public function hooks()
    {
        // Functions for cart actions - ensure they have a priority before addons (10).
        add_filter('woocommerce_is_purchasable', [$this, 'is_purchasable'], 5, 2);
        add_filter('woocommerce_add_cart_item_data', [$this, 'add_cart_item_data'], 5, 3);
        add_filter('woocommerce_get_cart_item_from_session', [$this, 'get_cart_item_from_session'], 11, 2);
        add_filter('woocommerce_cart_loaded_from_session', [$this, 'modify_product_data_in_memory'], 11, 1);
        add_filter('woocommerce_add_to_cart_validation', [$this, 'validate_add_cart_item'], 5, 6);
        // Re-validate prices in cart.
        add_action('woocommerce_check_cart_items', [$this, 'check_cart_items']);
        // Hook early into the cart calculations to modify prices with customer's custom values.
        add_action('woocommerce_before_calculate_totals', [$this, 'add_calculation_price_filter'], 0);
        add_action('woocommerce_calculate_totals', [$this, 'remove_calculation_price_filter'], 0);
        add_action('woocommerce_after_calculate_totals', [$this, 'remove_calculation_price_filter'], 0);
    }
    public function add_calculation_price_filter(): void
    {
        // First, prepare the product objects in the cart
        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
            if (isset($cart_item['cpw']) && $cart_item['data'] instanceof \WC_Product) {
                $this->cpw_prices[$cart_item['data']->get_id()] = $cart_item['cpw'];
            }
        }
        if (count($this->cpw_prices) === 0) {
            return;
        }
        foreach ($this->price_filters as $filter) {
            add_filter($filter, [$this, 'set_cpw_price_for_product'], 20, 2);
        }
    }
    public function remove_calculation_price_filter(): void
    {
        foreach ($this->price_filters as $filter) {
            remove_filter($filter, [$this, 'set_cpw_price_for_product'], 20);
        }
    }
    /**
     * @param float $price
     * @param \WC_Product $product
     *
     * @return float
     */
    public function set_cpw_price_for_product($price, $product)
    {
        if (isset($this->cpw_prices[$product->get_id()])) {
            return $this->cpw_prices[$product->get_id()];
        }
        return $price;
    }
    /**
     * Override woo's is_purchasable in cases of cpw products.
     *
     * @param bool       $is_purchasable
     * @param WC_Product $product
     *
     * @return  boolean
     * @since 1.0
     */
    public function is_purchasable(bool $is_purchasable, WC_Product $product): bool
    {
        if (!Helper::product_supports_cpw($product)) {
            return $is_purchasable;
        }
        /*
         * All CPW products should be purchasable.
         *
         * HACK: The exception is all-products woocommerce block, wchich does not respect
         * add_to_cart.url property and tries to add the product to the cart (ajax) anyway,
         * instead of redirecting it to the product page. To prevent that we can make it
         * unpurchasable for that specific case
         */
        global $wp;
        if (isset($wp->query_vars['rest_route']) && \false !== strpos($wp->query_vars['rest_route'], '/products')) {
            // '/wc/store/v1/products'
            return \false;
        }
        return \true;
    }
    /**
     * Add cart session data.
     *
     * @param array $cart_item_data extra cart item data we want to pass into the item.
     * @param int   $product_id     contains the id of the product to add to the cart.
     * @param int   $variation_id   ID of the variation being added to the cart.
     *
     * @since 1.0
     */
    public function add_cart_item_data(array $cart_item_data, $product_id, $variation_id): array
    {
        // TODO: redirecting should be separated from setting cart item data. And we need to stress somewhere that we DO want to save the price in cart session (for easy restore, etc)
        if (!is_scalar($product_id) || !is_scalar($variation_id)) {
            return $cart_item_data;
        }
        $product_id = (int) $product_id;
        $variation_id = (int) $variation_id;
        // An NYP item can either be a product or variation.
        $cpw_id = $variation_id ? $variation_id : $product_id;
        $suffix = Helper::get_suffix($cpw_id);
        $product = Helper::maybe_get_product_instance($cpw_id);
        // get_posted_price() removes the thousands separators.
        $posted_price = Helper::get_posted_price($product, $suffix);
        // Is this an NYP item?
        if (Helper::is_cpw($cpw_id) && $posted_price) {
            // Updating container in cart?
            if (isset($_POST['update-price'], $_POST['_cpwnonce']) && wp_verify_nonce(sanitize_key($_POST['_cpwnonce']), 'cpw-nonce')) {
                $updating_cart_key = wc_clean(wp_unslash($_POST['update-price']));
                if (WC()->cart->find_product_in_cart($updating_cart_key)) {
                    // Remove.
                    WC()->cart->remove_cart_item($updating_cart_key);
                    // Redirect to cart and edit notice.
                    add_filter('woocommerce_add_to_cart_redirect', fn() => wc_get_cart_url());
                    add_filter('wc_add_to_cart_message_html', fn() => __('Cart updated.', 'custom-price-for-woocommerce'));
                }
            }
            // No need to check is_cpw b/c this has already been validated by validate_add_cart_item().
            $cart_item_data['cpw'] = (float) $posted_price;
        }
        // Add the subscription billing period (the input name is cpw-period).
        $posted_period = Helper::get_posted_period($product, $suffix);
        if (Helper::is_subscription($cpw_id) && Helper::is_billing_period_variable($cpw_id) && $posted_period && array_key_exists($posted_period, Helper::get_subscription_period_strings())) {
            $cart_item_data['cpw_period'] = $posted_period;
        }
        return $cart_item_data;
    }
    /**
     * Adjust the product based on cart session data.
     *
     * @param array $cart_item $cart_item['data'] is product object in session
     * @param array $values    cart item array
     *
     * @since 1.0
     */
    public function get_cart_item_from_session(array $cart_item, array $values): array
    {
        // No need to check is_cpw b/c this has already been validated by validate_add_cart_item().
        if (isset($values['cpw'])) {
            $cart_item['cpw'] = $values['cpw'];
            // Add the subscription billing period.
            if (Helper::is_subscription($cart_item['data']) && isset($values['cpw_period']) && array_key_exists($values['cpw_period'], Helper::get_subscription_period_strings())) {
                $cart_item['cpw_period'] = $values['cpw_period'];
            }
        }
        return $cart_item;
    }
    /**
     * We have cart object with custom prices submitted by the user. To fully reflect that in the
     * UI, i.e. displayed price of the product, we need to temporarily modify product
     * values. This only works for product objects that are in the cart. Under no circumstances
     * this method can call `\WC_Product::save()`, persisting the changes!
     *
     * @param \WC_Cart $cart
     */
    public function modify_product_data_in_memory($cart)
    {
        foreach ($cart->get_cart() as $cart_item) {
            if (!isset($cart_item['cpw'], $cart_item['data'])) {
                continue;
            }
            $product = $cart_item['data'];
            $product->set_regular_price($cart_item['cpw']);
            $product->set_price($cart_item['cpw']);
            $product->set_sale_price($cart_item['cpw']);
            // Subscription-specific price and variable billing period.
            if ($product->is_type(['subscription', 'subscription_variation'])) {
                $product->update_meta_data('_subscription_price', $cart_item['cpw']);
                if (Helper::is_billing_period_variable($product) && isset($cart_item['cpw_period'])) {
                    // Length may need to be re-calculated. Hopefully no one is using the length but who knows.
                    // v3.1.3 disables the length selector when in variable billing mode.
                    $original_period = \WC_Subscriptions_Product::get_period($product);
                    $original_length = \WC_Subscriptions_Product::get_length($product);
                    if ($original_length > 0 && $original_period && $cart_item['cpw_period'] !== $original_period) {
                        $factors = Helper::annual_price_factors();
                        $new_length = $original_length * $factors[$cart_item['cpw_period']] / $factors[$original_period];
                        $product->update_meta_data('_subscription_length', floor($new_length));
                    }
                    // Set period to the chosen period.
                    $product->update_meta_data('_subscription_period', $cart_item['cpw_period']);
                    // Variable billing period is always a "per" interval.
                    $product->update_meta_data('_subscription_period_interval', 1);
                }
            }
        }
    }
    /**
     * Validate an NYP product before adding to cart.
     *
     * @param        $passed
     * @param int    $product_id     - Contains the ID of the product.
     * @param int    $quantity       - Contains the quantity of the item.
     * @param string $variation_id   - Contains the ID of the variation.
     * @param string $variations
     * @param array  $cart_item_data - Extra cart item data we want to pass into the item.
     *
     * @return bool
     * @since 1.0
     */
    public function validate_add_cart_item($passed, int $product_id, int $quantity, $variation_id = '', $variations = '', $cart_item_data = [])
    {
        $cpw_id = $variation_id ? $variation_id : $product_id;
        $product = wc_get_product($cpw_id);
        if (!Helper::product_supports_cpw($product)) {
            return $passed;
        }
        $suffix = Helper::get_suffix($cpw_id);
        // Get_posted_price() runs the price through the standardize_number() helper.
        $price = isset($cart_item_data['cpw']) ? $cart_item_data['cpw'] : Helper::get_posted_price($product, $suffix);
        // Get the posted billing period.
        $period = isset($cart_item_data['cpw_period']) ? $cart_item_data['cpw_period'] : Helper::get_posted_period($product, $suffix);
        return $this->validate_price($product, $quantity, $price, $period);
    }
    /**
     * Re-validate prices on cart load.
     * Specifically we are looking to prevent smart/quick pay gateway buttons completing an order that is invalid.
     */
    public function check_cart_items()
    {
        foreach (WC()->cart->cart_contents as $cart_item) {
            if (isset($cart_item['cpw'])) {
                $period = isset($cart_item['cpw_period']) ? $cart_item['cpw_period'] : '';
                $this->validate_price($cart_item['data'], $cart_item['quantity'], $cart_item['cpw'], $period, 'cart');
            }
        }
    }
    /**
     * Validates the product price
     *
     * @param WC_Product|int $product      The product or product ID.
     * @param int            $quantity     The quantity.
     * @param string         $price        The price to validate.
     * @param string         $period       The billing period for subscriptions.
     * @param string         $context      The context of the validation (e.g., 'add-to-cart').
     * @param bool           $return_error If true, returns the error message string instead of adding a WC notice.
     *
     * @return ($return_error is true ? string : bool) True on success, false or an error string on failure.
     */
    public function validate_price($product, $quantity, string $price, $period = '', $context = 'add-to-cart', $return_error = \false)
    {
        $product = Helper::maybe_get_product_instance($product);
        if (!$product instanceof WC_Product) {
            $notice = Helper::error_message('invalid-product');
            return $this->handle_validation_error($notice, $return_error);
        }
        $product_title = $product->get_title();
        $minimum = Helper::get_minimum_price($product);
        $maximum = Helper::get_maximum_price($product);
        if (!is_numeric($price) || is_infinite($price) || floatval($price) < 0) {
            $notice = Helper::error_message('invalid', ['%%TITLE%%' => $product_title], $product, $context);
            return $this->handle_validation_error($notice, $return_error);
        }
        if ($minimum && $period && Helper::is_subscription($product) && Helper::is_billing_period_variable($product)) {
            $notice = $this->validate_subscription_price($product, $price, $period, $minimum, $product_title, $context);
            if ($notice) {
                return $this->handle_validation_error($notice, $return_error);
            }
            // If the subscription price is valid, we skip the other checks
            return \true;
        }
        if ($minimum && floatval($price) < floatval($minimum)) {
            $error_template = Helper::is_minimum_hidden($product) ? 'hide_minimum' : 'minimum';
            $notice = Helper::error_message($error_template, ['%%TITLE%%' => $product_title, '%%MINIMUM%%' => wc_price($minimum)], $product, $context);
            return $this->handle_validation_error($notice, $return_error);
        }
        if ($maximum && floatval($price) > floatval($maximum)) {
            $error_template = '' !== $context ? 'maximum-' . $context : 'maximum';
            $notice = Helper::error_message($error_template, ['%%TITLE%%' => $product_title, '%%MAXIMUM%%' => wc_price($maximum)], $product, $context);
            return $this->handle_validation_error($notice, $return_error);
        }
        return \true;
    }
    /**
     * Helper method to validate variable billing subscription price.
     *
     * @return string|null The error message on failure, or null on success.
     */
    private function validate_subscription_price($product, string $price, string $period, $minimum, string $product_title, string $context): ?string
    {
        // Annualize prices to safely compare them.
        $minimum_period = Helper::get_minimum_billing_period($product);
        $minimum_annual = Helper::annualize_price($minimum, $minimum_period);
        $annual_price = Helper::annualize_price($price, $period);
        // If the annualized entered price is less than the minimum, it's an error.
        if ($annual_price < $minimum_annual) {
            $factors = Helper::annual_price_factors();
            // Calculate the minimum price for the user-entered period for a clearer error message.
            if (isset($factors[$period])) {
                $error_price = $minimum_annual / $factors[$period];
                $error_period = $period;
            } else {
                // Fallback to the saved minimum price and period.
                $error_price = $minimum;
                $error_period = $minimum_period;
            }
            $minimum_error = wc_price($error_price) . ' / ' . $error_period;
            $error_template = Helper::is_minimum_hidden($product) ? 'hide_minimum' : 'minimum';
            return Helper::error_message($error_template, ['%%TITLE%%' => $product_title, '%%MINIMUM%%' => $minimum_error], $product, $context);
        }
        return null;
        // Price is valid.
    }
    /**
     * Helper method to handle the result of a validation failure.
     *
     * It either returns the error string or adds a WooCommerce notice and returns false,
     * centralizing the logic from the original catch block.
     *
     * @param string $notice       The error message.
     * @param bool   $return_error If true, return the notice string.
     * @return string|false
     */
    private function handle_validation_error(string $notice, bool $return_error)
    {
        if ($return_error) {
            return $notice;
        }
        if ($notice) {
            wc_add_notice($notice, 'error');
        }
        return \false;
    }
}
