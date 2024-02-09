<?php declare( strict_types=1 );

namespace Wikibase\Repo\RestApi\Application\UseCaseRequestValidation;

use InvalidArgumentException;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\Repo\RestApi\Application\UseCases\UseCaseError;

/**
 * @license GPL-2.0-or-later
 */
class ItemIdRequestValidatingDeserializer {
	/**
	 * @throws UseCaseError
	 */
	public function validateAndDeserialize( ItemIdRequest $request ): ItemId {
		try {
			return new ItemId( $request->getItemId() );
		} catch ( InvalidArgumentException $e ) {
			throw new UseCaseError( UseCaseError::INVALID_ITEM_ID, "Not a valid item ID: {$request->getItemId()}" );
		}
	}

}
