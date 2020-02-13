<?php

namespace Wikibase\DataModel\Services\Term;

use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Term\Fingerprint;

interface ItemTermStoreWriter {

	/**
	 * Updates the stored terms for the specified item.
	 * @throws TermStoreException
	 */
	public function storeTerms( ItemId $itemId, Fingerprint $terms );

	/**
	 * @throws TermStoreException
	 */
	public function deleteTerms( ItemId $itemId );

}
