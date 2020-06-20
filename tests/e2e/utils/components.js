/**
 * @format
 */

const config = require( 'config' );

const verifyAndPublish = async () => {
	// Wait for auto save
	await page.waitFor( 2000 );

	// Publish product
	await expect( page ).toClick( '#publish' );
	await page.waitForSelector( '.updated.notice' );

	// Verify
	await expect( page ).toMatchElement( '.updated.notice', { text: 'Product published.' } );
};

const verifyAndUpdate = async () => {
	// Wait for auto save
	await page.waitFor( 2000 );

	// Publish product
	await expect( page ).toClick( '#publish' );
	await page.waitForSelector( '.updated.notice' );

	// Verify
	await expect( page ).toMatchElement( '.updated.notice', { text: 'Product updated.' } );
};

export {
	verifyAndPublish,
	verifyAndUpdate,
};