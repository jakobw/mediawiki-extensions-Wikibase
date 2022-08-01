'use strict';

const { assert, action, utils, clientFactory } = require( 'api-testing' );
const entityHelper = require( '../helpers/entityHelper' );
const { RequestBuilder } = require( '../helpers/RequestBuilder' );
const { requireExtensions } = require( '../../../../../tests/api-testing/utils' );

function newReplaceStatementRequestBuilder( statementId, statement ) {
	return new RequestBuilder()
		.withRoute( 'PUT', '/statements/{statement_id}' )
		.withPathParam( 'statement_id', statementId )
		.withHeader( 'content-type', 'application/json' )
		.withJsonBodyParam( 'statement', statement );
}

function newStatementWithRandomStringValue( property ) {
	return {
		mainsnak: {
			snaktype: 'value',
			datavalue: {
				type: 'string',
				value: 'random-string-value-' + utils.uniq()
			},
			property
		},
		type: 'statement'
	};
}

function makeEtag( ...revisionIds ) {
	return revisionIds.map( ( revId ) => `"${revId}"` ).join( ',' );
}

describe( 'PUT /statements/{statement_id}', () => {
	let testItemId;
	let testStatementId;
	let testPropertyId;
	let originalLastModified;
	let originalRevisionId;

	function assertValid200Response( response ) {
		assert.strictEqual( response.status, 200 );
		assert.strictEqual( response.body.id, testStatementId );
		assert.strictEqual( response.header[ 'content-type' ], 'application/json' );
		assert.isAbove( new Date( response.header[ 'last-modified' ] ), originalLastModified );
		assert.notStrictEqual( response.header.etag, makeEtag( originalRevisionId ) );
	}

	before( async () => {
		testPropertyId = ( await entityHelper.createUniqueStringProperty() ).entity.id;
		const createEntityResponse = await entityHelper.createEntity( 'item', {
			claims: [ {
				mainsnak: {
					snaktype: 'novalue',
					property: testPropertyId
				},
				type: 'statement'
			} ]
		} );
		testItemId = createEntityResponse.entity.id;
		testStatementId = createEntityResponse.entity.claims[ testPropertyId ][ 0 ].id;

		const testItemCreationMetadata = await entityHelper.getLatestEditMetadata( testItemId );
		originalLastModified = new Date( testItemCreationMetadata.timestamp );
		originalRevisionId = testItemCreationMetadata.revid;

		// wait 1s before modifications to verify the last-modified timestamps are different
		await new Promise( ( resolve ) => {
			setTimeout( resolve, 1000 );
		} );
	} );

	describe( '200 success response ', () => {
		it( 'can replace a statement to an item with edit metadata omitted', async () => {
			const statementSerialization = newStatementWithRandomStringValue( testPropertyId );
			const response = await newReplaceStatementRequestBuilder(
				testStatementId,
				statementSerialization
			).assertValidRequest().makeRequest();

			assertValid200Response( response );

			assert.deepEqual(
				response.body.mainsnak.datavalue,
				statementSerialization.mainsnak.datavalue
			);
			const { comment } = await entityHelper.getLatestEditMetadata( testItemId );
			assert.strictEqual( comment, 'Wikibase REST API edit' );
		} );

		it( 'can replace a statement to an item with edit metadata provided', async () => {
			const tag = await action.makeTag( 'e2e test tag', 'Created during e2e test' );
			const editSummary = 'omg look i made an edit';
			const statementSerialization = newStatementWithRandomStringValue( testPropertyId );
			const response = await newReplaceStatementRequestBuilder(
				testStatementId,
				statementSerialization
			).withJsonBodyParam( 'tags', [ tag ] )
				.withJsonBodyParam( 'bot', true )
				.withJsonBodyParam( 'comment', editSummary )
				.assertValidRequest()
				.makeRequest();

			assertValid200Response( response );
			assert.deepEqual(
				response.body.mainsnak.datavalue,
				statementSerialization.mainsnak.datavalue
			);

			const editMetadata = await entityHelper.getLatestEditMetadata( testItemId );
			assert.deepEqual( editMetadata.tags, [ tag ] );
			assert.property( editMetadata, 'bot' );
			assert.strictEqual( editMetadata.comment, editSummary );
		} );

		it( 'repeating the same request only results in one edit', async () => {
			const statementSerialization = newStatementWithRandomStringValue( testPropertyId );
			const requestTemplate = newReplaceStatementRequestBuilder(
				testStatementId,
				statementSerialization
			).assertValidRequest();

			const response1 = await requestTemplate.makeRequest();
			const response2 = await requestTemplate.makeRequest();

			assertValid200Response( response1 );
			assertValid200Response( response2 );

			assert.strictEqual( response1.headers.etag, response2.headers.etag );
			assert.strictEqual( response1.headers[ 'last-modified' ], response2.headers[ 'last-modified' ] );
		} );

		it( 'replaces the statement in place without changing the order', async () => {
			// This is tested here by creating an item with 3 statements, replacing the middle one
			// and then checking that it's still in the middle afterwards.
			const item = ( await entityHelper.createEntity( 'item', {
				claims: [
					newStatementWithRandomStringValue( testPropertyId ),
					newStatementWithRandomStringValue( testPropertyId ),
					newStatementWithRandomStringValue( testPropertyId )
				]
			} ) ).entity;
			const originalSecondStatement = item.claims[ testPropertyId ][ 1 ];
			const newSecondStatement = newStatementWithRandomStringValue( testPropertyId );

			await newReplaceStatementRequestBuilder(
				originalSecondStatement.id,
				newSecondStatement
			).makeRequest();

			const actualSecondStatement = ( await new RequestBuilder()
				.withRoute( 'GET', '/entities/items/{item_id}/statements' )
				.withPathParam( 'item_id', item.id )
				.makeRequest() ).body[ testPropertyId ][ 1 ];

			assert.strictEqual( actualSecondStatement.id, originalSecondStatement.id );
			assert.strictEqual(
				actualSecondStatement.mainsnak.datavalue.value,
				newSecondStatement.mainsnak.datavalue.value
			);
			assert.notEqual(
				actualSecondStatement.mainsnak.datavalue.value,
				originalSecondStatement.mainsnak.datavalue.value
			);
		} );

	} );

	describe( '404 error response', () => {
		it( 'statement not found on item', async () => {
			const statementId = testItemId + '$AAAAAAAA-BBBB-CCCC-DDDD-EEEEEEEEEEEE';
			const response = await newReplaceStatementRequestBuilder( statementId )
				.withJsonBodyParam( 'statement', newStatementWithRandomStringValue( testPropertyId ) )
				.assertValidRequest()
				.makeRequest();

			assert.equal( response.status, 404 );
			assert.header( response, 'Content-Language', 'en' );
			assert.equal( response.body.code, 'statement-not-found' );
			assert.include( response.body.message, statementId );
		} );

		it( 'item not found', async () => {
			const statementId = 'Q9999999$AAAAAAAA-BBBB-CCCC-DDDD-EEEEEEEEEEEE';
			const response = await newReplaceStatementRequestBuilder( statementId )
				.withJsonBodyParam( 'statement', newStatementWithRandomStringValue( testPropertyId ) )
				.assertValidRequest()
				.makeRequest();

			assert.equal( response.status, 404 );
			assert.header( response, 'Content-Language', 'en' );
			assert.equal( response.body.code, 'statement-not-found' );
			assert.include( response.body.message, statementId );
		} );
	} );

	describe( 'authentication', () => {

		it( 'has an X-Authenticated-User header with the logged in user', async () => {
			const mindy = await action.mindy();
			const response = await clientFactory.getRESTClient( 'rest.php/wikibase/v0', mindy ).put(
				`/statements/${testStatementId}`,
				{ statement: newStatementWithRandomStringValue( testPropertyId ) },
				{ 'content-type': 'application/json' }
			);

			assertValid200Response( response );
			assert.header( response, 'X-Authenticated-User', mindy.username );
		} );

		describe.skip( 'OAuth', () => { // Skipping due to apache auth header issues. See T305709
			before( requireExtensions( [ 'OAuth' ] ) );

			it( 'responds with an error given an invalid bearer token', async () => {
				const response = newReplaceStatementRequestBuilder(
					testItemId,
					newStatementWithRandomStringValue( testPropertyId )
				)
					.withHeader( 'Authorization', 'Bearer this-is-an-invalid-token' )
					.makeRequest();

				assert.strictEqual( response.status, 403 );
			} );

		} );

	} );

} );