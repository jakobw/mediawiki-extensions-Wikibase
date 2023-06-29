'use strict';

const { assert } = require( 'api-testing' );
const { expect } = require( '../helpers/chaiHelper' );
const entityHelper = require( '../helpers/entityHelper' );
const {
	newGetItemStatementRequestBuilder,
	newGetStatementRequestBuilder
} = require( '../helpers/RequestBuilderFactory' );
const { makeEtag } = require( '../helpers/httpHelper' );

describe( 'GET statement', () => {
	let testItemId;

	let testStatement;
	let testStatementWithDeletedProperty;

	let testLastModified;
	let testRevisionId;

	function assertValid200Response( response, statement ) {
		expect( response ).to.have.status( 200 );
		assert.equal( response.body.id, statement.id );
		assert.equal( response.header[ 'last-modified' ], testLastModified );
		assert.equal( response.header.etag, makeEtag( testRevisionId ) );
	}

	before( async () => {
		const testPropertyId = ( await entityHelper.createUniqueStringProperty() ).entity.id;
		const testPropertyIdToDelete = ( await entityHelper.createUniqueStringProperty() ).entity.id;

		const createItemResponse = await entityHelper.createItemWithStatements( [
			entityHelper.newLegacyStatementWithRandomStringValue( testPropertyId ),
			entityHelper.newLegacyStatementWithRandomStringValue( testPropertyIdToDelete )
		] );

		testItemId = createItemResponse.entity.id;
		testStatement = createItemResponse.entity.claims[ testPropertyId ][ 0 ];

		testStatementWithDeletedProperty = createItemResponse.entity.claims[ testPropertyIdToDelete ][ 0 ];
		await entityHelper.deleteProperty( testPropertyIdToDelete );

		const testItemCreationMetadata = await entityHelper.getLatestEditMetadata( testItemId );
		testLastModified = testItemCreationMetadata.timestamp;
		testRevisionId = testItemCreationMetadata.revid;
	} );

	[
		( statementId ) => newGetItemStatementRequestBuilder( testItemId, statementId ),
		newGetStatementRequestBuilder
	].forEach( ( newRequestBuilder ) => {
		describe( newRequestBuilder().getRouteDescription(), () => {
			it( 'can GET a statement with metadata', async () => {
				const response = await newRequestBuilder( testStatement.id )
					.assertValidRequest()
					.makeRequest();

				assertValid200Response( response, testStatement );
			} );

			it( 'can get a statement with a deleted property', async () => {
				const response = await newGetStatementRequestBuilder( testStatementWithDeletedProperty.id )
					.assertValidRequest()
					.makeRequest();

				assertValid200Response( response, testStatementWithDeletedProperty );
				assert.equal( response.body.property[ 'data-type' ], null );
			} );

			describe( '400 error response', () => {
				it( 'statement ID contains invalid entity ID', async () => {
					const statementId = 'X123$AAAAAAAA-BBBB-CCCC-DDDD-EEEEEEEEEEEE';
					const response = await newRequestBuilder( statementId )
						.assertInvalidRequest()
						.makeRequest();

					expect( response ).to.have.status( 400 );
					assert.header( response, 'Content-Language', 'en' );
					assert.equal( response.body.code, 'invalid-statement-id' );
					assert.include( response.body.message, statementId );
				} );

				it( 'statement ID is invalid format', async () => {
					const statementId = 'not-a-valid-format';
					const response = await newRequestBuilder( statementId )
						.assertInvalidRequest()
						.makeRequest();

					expect( response ).to.have.status( 400 );
					assert.header( response, 'Content-Language', 'en' );
					assert.equal( response.body.code, 'invalid-statement-id' );
					assert.include( response.body.message, statementId );
				} );

				it( 'statement is not on an item', async () => {
					const statementId = 'P123$AAAAAAAA-BBBB-CCCC-DDDD-EEEEEEEEEEEE';
					const response = await newRequestBuilder( statementId )
						.assertValidRequest()
						.makeRequest();

					expect( response ).to.have.status( 400 );
					assert.header( response, 'Content-Language', 'en' );
					assert.equal( response.body.code, 'invalid-statement-id' );
					assert.include( response.body.message, statementId );
				} );
			} );

			describe( '404 error response', () => {
				it( 'statement not found on item', async () => {
					const statementId = testItemId + '$AAAAAAAA-BBBB-CCCC-DDDD-EEEEEEEEEEEE';
					const response = await newRequestBuilder( statementId )
						.assertValidRequest()
						.makeRequest();

					expect( response ).to.have.status( 404 );
					assert.header( response, 'Content-Language', 'en' );
					assert.equal( response.body.code, 'statement-not-found' );
					assert.include( response.body.message, statementId );
				} );

				it( 'statement subject is a redirect', async () => {
					const redirectSource = await entityHelper.createRedirectForItem( testItemId );
					const statementId = redirectSource + '$AAAAAAAA-BBBB-CCCC-DDDD-EEEEEEEEEEEE';
					const response = await newRequestBuilder( statementId )
						.assertValidRequest()
						.makeRequest();

					expect( response ).to.have.status( 404 );
					assert.header( response, 'Content-Language', 'en' );
					assert.equal( response.body.code, 'statement-not-found' );
					assert.include( response.body.message, statementId );
				} );
			} );
		} );
	} );

	describe( 'long route specific errors', () => {
		it( 'responds 400 for invalid Item ID', async () => {
			const itemId = 'X123';
			const statementId = 'Q123$AAAAAAAA-BBBB-CCCC-DDDD-EEEEEEEEEEEE';
			const response = await newGetItemStatementRequestBuilder( itemId, statementId )
				.assertInvalidRequest()
				.makeRequest();

			expect( response ).to.have.status( 400 );
			assert.header( response, 'Content-Language', 'en' );
			assert.equal( response.body.code, 'invalid-item-id' );
			assert.include( response.body.message, itemId );
		} );

		it( 'responds item-not-found if item does not exist but statement does', async () => {
			const itemId = 'Q999999';
			const response = await newGetItemStatementRequestBuilder( itemId, testStatement.id )
				.assertValidRequest()
				.makeRequest();

			expect( response ).to.have.status( 404 );
			assert.header( response, 'Content-Language', 'en' );
			assert.equal( response.body.code, 'item-not-found' );
			assert.include( response.body.message, itemId );
		} );

		it( 'responds item-not-found if item does not exist and statement subject does, ' +
			'but statement does not', async () => {
			const itemId = 'Q999999';
			const statementId = `${itemId}$AAAAAAAA-BBBB-CCCC-DDDD-EEEEEEEEEEEE`;
			const response = await newGetItemStatementRequestBuilder( itemId, statementId )
				.assertValidRequest()
				.makeRequest();

			expect( response ).to.have.status( 404 );
			assert.header( response, 'Content-Language', 'en' );
			assert.equal( response.body.code, 'item-not-found' );
			assert.include( response.body.message, itemId );
		} );

		it( 'responds item-not-found if neither item nor statement nor its subject exist', async () => {
			const itemId = 'Q999999';
			const statementId = 'Q999999$AAAAAAAA-BBBB-CCCC-DDDD-EEEEEEEEEEEE';
			const response = await newGetItemStatementRequestBuilder( itemId, statementId )
				.assertValidRequest()
				.makeRequest();

			expect( response ).to.have.status( 404 );
			assert.header( response, 'Content-Language', 'en' );
			assert.equal( response.body.code, 'item-not-found' );
			assert.include( response.body.message, itemId );
		} );

		it( 'responds statement-not-found if item exists but statement subject does not', async () => {
			const requestedItemId = ( await entityHelper.createEntity( 'item', {} ) ).entity.id;
			const statementId = 'Q999999$AAAAAAAA-BBBB-CCCC-DDDD-EEEEEEEEEEEE';
			const response = await newGetItemStatementRequestBuilder( requestedItemId, statementId )
				.assertValidRequest()
				.makeRequest();

			expect( response ).to.have.status( 404 );
			assert.equal( response.body.code, 'statement-not-found' );
			assert.include( response.body.message, statementId );
		} );

		it( 'responds statement-not-found if item and subject exists but statement does not', async () => {
			const requestedItemId = ( await entityHelper.createEntity( 'item', {} ) ).entity.id;
			const statementId = `${requestedItemId}$AAAAAAAA-BBBB-CCCC-DDDD-EEEEEEEEEEEE`;
			const response = await newGetItemStatementRequestBuilder( requestedItemId, statementId )
				.assertValidRequest()
				.makeRequest();

			expect( response ).to.have.status( 404 );
			assert.equal( response.body.code, 'statement-not-found' );
			assert.include( response.body.message, statementId );
		} );

		it( 'responds statement-not-found if requested Item and Statement exist, but do not match', async () => {
			const requestedItemId = ( await entityHelper.createEntity( 'item', {} ) ).entity.id;
			const response = await newGetItemStatementRequestBuilder(
				requestedItemId,
				testStatement.id
			).assertValidRequest().makeRequest();

			expect( response ).to.have.status( 404 );
			assert.equal( response.body.code, 'statement-not-found' );
			assert.include( response.body.message, testStatement.id );
		} );
	} );

	describe( 'short route specific errors', () => {
		it( 'responds 404 if item not found', async () => {
			const statementId = 'Q999999$AAAAAAAA-BBBB-CCCC-DDDD-EEEEEEEEEEEE';
			const response = await newGetStatementRequestBuilder( statementId )
				.assertValidRequest()
				.makeRequest();

			expect( response ).to.have.status( 404 );
			assert.header( response, 'Content-Language', 'en' );
			assert.equal( response.body.code, 'statement-not-found' );
			assert.include( response.body.message, statementId );
		} );
	} );

} );