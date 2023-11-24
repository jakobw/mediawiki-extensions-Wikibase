'use strict';

const { assert, utils, action } = require( 'api-testing' );
const { expect } = require( '../helpers/chaiHelper' );
const entityHelper = require( '../helpers/entityHelper' );
const { newPatchPropertyDescriptionsRequestBuilder } = require( '../helpers/RequestBuilderFactory' );
const { makeEtag } = require( '../helpers/httpHelper' );
const { formatTermsEditSummary } = require( '../helpers/formatEditSummaries' );
const testValidatesPatch = require( '../helpers/testValidatesPatch' );

function assertValidErrorResponse( response, statusCode, responseBodyErrorCode, context = null ) {
	expect( response ).to.have.status( statusCode );
	assert.header( response, 'Content-Language', 'en' );
	assert.strictEqual( response.body.code, responseBodyErrorCode );
	if ( context === null ) {
		assert.notProperty( response.body, 'context' );
	} else {
		assert.deepStrictEqual( response.body.context, context );
	}
}

describe( newPatchPropertyDescriptionsRequestBuilder().getRouteDescription(), () => {

	let testPropertyId;
	let testEnDescription;
	let originalLastModified;
	let originalRevisionId;
	const languageWithExistingDescription = 'en';

	before( async function () {
		testEnDescription = `some-description-${utils.uniq()}`;

		testPropertyId = ( await entityHelper.createEntity( 'property', {
			datatype: 'string',
			descriptions: [ { language: languageWithExistingDescription, value: testEnDescription } ]
		} ) ).entity.id;

		const testPropertyCreationMetadata = await entityHelper.getLatestEditMetadata( testPropertyId );
		originalLastModified = new Date( testPropertyCreationMetadata.timestamp );
		originalRevisionId = testPropertyCreationMetadata.revid;

		// wait 1s before modifying to ensure subsequent last-modified timestamps are different
		await new Promise( ( resolve ) => {
			setTimeout( resolve, 1000 );
		} );
	} );

	describe( '200 OK', () => {
		it( 'can add a description', async () => {
			const description = `neues deutsches description ${utils.uniq()}`;
			const response = await newPatchPropertyDescriptionsRequestBuilder(
				testPropertyId,
				[ { op: 'add', path: '/de', value: description } ]
			).makeRequest();

			expect( response ).to.have.status( 200 );
			assert.strictEqual( response.body.de, description );
			assert.strictEqual( response.header[ 'content-type' ], 'application/json' );
			assert.isAbove( new Date( response.header[ 'last-modified' ] ), originalLastModified );
			assert.notStrictEqual( response.header.etag, makeEtag( originalRevisionId ) );
		} );

		it( 'can patch labels with edit metadata', async () => {
			const description = `new arabic label ${utils.uniq()}`;
			const user = await action.robby(); // robby is a bot
			const tag = await action.makeTag( 'e2e test tag', 'Created during e2e test' );
			const comment = 'I made a patch';
			const response = await newPatchPropertyDescriptionsRequestBuilder(
				testPropertyId,
				[ { op: 'add', path: '/ar', value: description } ]
			).withJsonBodyParam( 'tags', [ tag ] )
				.withJsonBodyParam( 'bot', true )
				.withJsonBodyParam( 'comment', comment )
				.withUser( user )
				.assertValidRequest().makeRequest();

			expect( response ).to.have.status( 200 );
			assert.strictEqual( response.body.ar, description );
			assert.strictEqual( response.header[ 'content-type' ], 'application/json' );
			assert.isAbove( new Date( response.header[ 'last-modified' ] ), originalLastModified );
			assert.notStrictEqual( response.header.etag, makeEtag( originalRevisionId ) );

			const editMetadata = await entityHelper.getLatestEditMetadata( testPropertyId );
			assert.include( editMetadata.tags, tag );
			assert.property( editMetadata, 'bot' );
			assert.strictEqual(
				editMetadata.comment,
				formatTermsEditSummary( 'update-languages-short', 'ar', comment )
			);
		} );

		it( 'trims whitespace around the description', async () => {
			const description = `spacey ${utils.uniq()}`;
			const response = await newPatchPropertyDescriptionsRequestBuilder(
				testPropertyId,
				[ { op: 'add', path: '/de', value: `\t${description}  ` } ]
			).makeRequest();

			expect( response ).to.have.status( 200 );
			assert.strictEqual( response.body.de, description );
		} );
	} );

	describe( '400 error response', () => {

		it( 'invalid property id', async () => {
			const propertyId = testPropertyId.replace( 'P', 'Q' );
			const response = await newPatchPropertyDescriptionsRequestBuilder( propertyId, [] )
				.assertInvalidRequest().makeRequest();

			assertValidErrorResponse( response, 400, 'invalid-property-id', { 'property-id': propertyId } );
			assert.include( response.body.message, propertyId );
		} );

		testValidatesPatch( ( patch ) => newPatchPropertyDescriptionsRequestBuilder( testPropertyId, patch ) );

		it( 'invalid edit tag', async () => {
			const invalidEditTag = 'invalid tag';
			const response = await newPatchPropertyDescriptionsRequestBuilder( testPropertyId, [] )
				.withJsonBodyParam( 'tags', [ invalidEditTag ] ).assertValidRequest().makeRequest();

			assertValidErrorResponse( response, 400, 'invalid-edit-tag' );
			assert.include( response.body.message, invalidEditTag );
		} );

		it( 'invalid edit tag type', async () => {
			const response = await newPatchPropertyDescriptionsRequestBuilder( testPropertyId, [] )
				.withJsonBodyParam( 'tags', 'not an array' ).assertInvalidRequest().makeRequest();

			expect( response ).to.have.status( 400 );
			assert.strictEqual( response.body.code, 'invalid-request-body' );
			assert.strictEqual( response.body.fieldName, 'tags' );
			assert.strictEqual( response.body.expectedType, 'array' );
		} );

		it( 'invalid bot flag type', async () => {
			const response = await newPatchPropertyDescriptionsRequestBuilder( testPropertyId, [] )
				.withJsonBodyParam( 'bot', 'not boolean' ).assertInvalidRequest().makeRequest();

			expect( response ).to.have.status( 400 );
			assert.strictEqual( response.body.code, 'invalid-request-body' );
			assert.strictEqual( response.body.fieldName, 'bot' );
			assert.strictEqual( response.body.expectedType, 'boolean' );
		} );

		it( 'comment too long', async () => {
			const comment = 'x'.repeat( 501 );
			const response = await newPatchPropertyDescriptionsRequestBuilder( testPropertyId, [] )
				.withJsonBodyParam( 'comment', comment ).assertValidRequest().makeRequest();

			assertValidErrorResponse( response, 400, 'comment-too-long' );
			assert.include( response.body.message, '500' );
		} );

		it( 'invalid comment type', async () => {
			const response = await newPatchPropertyDescriptionsRequestBuilder( testPropertyId, [] )
				.withJsonBodyParam( 'comment', 1234 ).assertInvalidRequest().makeRequest();

			expect( response ).to.have.status( 400 );
			assert.strictEqual( response.body.code, 'invalid-request-body' );
			assert.strictEqual( response.body.fieldName, 'comment' );
			assert.strictEqual( response.body.expectedType, 'string' );
		} );
	} );

	describe( '404 error response', () => {
		it( 'property not found', async () => {
			const propertyId = 'P99999';
			const response = await newPatchPropertyDescriptionsRequestBuilder(
				propertyId,
				[
					{
						op: 'replace',
						path: '/en',
						value: utils.uniq()
					}
				]
			).assertValidRequest().makeRequest();

			expect( response ).to.have.status( 404 );
			assert.strictEqual( response.header[ 'content-language' ], 'en' );
			assert.strictEqual( response.body.code, 'property-not-found' );
			assert.include( response.body.message, propertyId );
		} );
	} );

	describe( '409 error response', () => {
		it( '"path" field target does not exist', async () => {
			const operation = { op: 'remove', path: '/path/does/not/exist' };

			const response = await newPatchPropertyDescriptionsRequestBuilder( testPropertyId, [ operation ] )
				.assertValidRequest().makeRequest();

			assertValidErrorResponse(
				response,
				409,
				'patch-target-not-found',
				{ field: 'path', operation: operation }
			);
			assert.include( response.body.message, operation.path );
		} );

		it( '"from" field target does not exist', async () => {
			const operation = { op: 'copy', from: '/path/does/not/exist', path: '/en' };

			const response = await newPatchPropertyDescriptionsRequestBuilder( testPropertyId, [ operation ] )
				.assertValidRequest().makeRequest();

			assertValidErrorResponse(
				response,
				409,
				'patch-target-not-found',
				{ field: 'from', operation: operation }
			);
			assert.include( response.body.message, operation.from );
		} );

		it( 'patch test condition failed', async () => {
			const operation = { op: 'test', path: '/en', value: 'incorrect' };
			const response = await newPatchPropertyDescriptionsRequestBuilder( testPropertyId, [ operation ] )
				.assertValidRequest().makeRequest();

			assertValidErrorResponse(
				response,
				409,
				'patch-test-failed',
				{ operation: operation, 'actual-value': testEnDescription }
			);
			assert.include( response.body.message, operation.path );
			assert.include( response.body.message, JSON.stringify( operation.value ) );
			assert.include( response.body.message, testEnDescription );
		} );
	} );
} );
