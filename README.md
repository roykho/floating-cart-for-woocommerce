woocommerce-deposits
====================

[![Build Status](https://travis-ci.com/woocommerce/woocommerce-deposits.svg?token=T9yqsTatu7i3Jk6Pvss7&branch=master)](https://travis-ci.com/woocommerce/woocommerce-deposits)

Add tracking numbers to orders allowing customers to track their orders via a link. Supports many shipping providers, as well as custom ones if neccessary via a regular link.

## NPM Scripts

WooCommerce Deposits utilizes npm scripts for task management utilities.

`npm run build` - Runs the tasks necessary for a release. These include building JavaScript, SASS, CSS minification, and language files.

### E2E Testing

The end to end tests rely on the [WooCommerce End to End Testing Environment](https://github.com/woocommerce/woocommerce/tree/master/tests/e2e/env).

One of the prerequisites is to install docker in your local environment: https://github.com/woocommerce/woocommerce/tree/master/tests/e2e#install-docker

Once docker is installed you can run the tests with the following commands:

1. `npm run docker:up`
2. `npm run test:e2e`
3. `npm run docker:down`

Alternatively the tests can be run without headless mode:
`npm run test:e2e-dev`

Note: Currently there is still work being done to improve the testing environment, so it's recommended to wait a while after the `docker:up` command to make sure it has completed setting up the test environment.
