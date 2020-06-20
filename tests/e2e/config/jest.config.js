/**
 * For a detailed explanation of configuration properties, visit:
 * https://jestjs.io/docs/en/configuration.html
 */

const path = require( 'path' );
const { jestConfig: baseE2Econfig } = require( '@woocommerce/e2e-environment' );

module.exports = {
	...baseE2Econfig,
	// Specify the path of your project's E2E tests here.
	roots: [ path.resolve( __dirname, '../specs' ) ],
};
