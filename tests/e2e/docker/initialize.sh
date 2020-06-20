#!/bin/bash

echo "Initializing WooCommerce Deposits E2E"

# Enable pretty permalinks.
wp rewrite structure '/%postname%/'

# Use storefront theme.
wp theme install storefront --activate
wp option update storefront_nux_dismissed 1

# Activate and setup WooCommerce.
wp plugin install woocommerce --activate

wp wc tool run install_pages --user=1
wp wc payment_gateway update cod --enabled=true --user=1

wp option update woocommerce_currency "USD"
wp option update woocommerce_default_country "US:CA"

wp user create customer customer@woocommercecoree2etestsuite.com --user_pass=password --role=customer

# Activate plugin to test.
wp plugin activate woocommerce-deposits