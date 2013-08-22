<?php

namespace Wikibase;

use Diff\Patcher;
use InvalidArgumentException;
use OutOfBoundsException;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\SimpleSiteLink;

/**
 * Represents a single Wikibase item.
 * See https://meta.wikimedia.org/wiki/Wikidata/Data_model#Items
 *
 * @since 0.1
 *
 * @file
 * @ingroup WikibaseDataModel
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class Item extends Entity {

	const ENTITY_TYPE = 'item';

	/**
	 * Adds a site link to the list of site links.
	 * If there already is a site link with the site id of the provided site link,
	 * then that one will be overridden by the provided one.
	 *
	 * @since 0.4
	 *
	 * @param SimpleSiteLink $siteLink
	 */
	public function addSimpleSiteLink( SimpleSiteLink $siteLink ) {
		$this->data['links'][$siteLink->getSiteId()] = $siteLink->getPageName();
	}

	/**
	 * Removes the sitelink with specified site ID if the Item has such a sitelink.
	 * A page name can be provided to have removal only happen when it matches what is set.
	 * A boolean is returned indicating if a link got removed or not.
	 *
	 * @since 0.1
	 *
	 * @param string $siteId the target site's id
	 * @param bool|string $pageName he target page's name (in normalized form)
	 *
	 * @return bool Success indicator
	 */
	public function removeSiteLink( $siteId, $pageName = false ) {
		if ( $pageName !== false ) {
			$success = array_key_exists( $siteId, $this->data['links'] ) && $this->data['links'][$siteId] === $pageName;
		}
		else {
			$success = array_key_exists( $siteId, $this->data['links'] );
		}

		if ( $success ) {
			unset( $this->data['links'][$siteId] );
		}

		return $success;
	}

	/**
	 * @since 0.4
	 *
	 * @return SimpleSiteLink[]
	 */
	public function getSimpleSiteLinks() {
		$links = array();

		foreach ( $this->data['links'] as $siteId => $pageName ) {
			$links[] = new SimpleSiteLink( $siteId, $pageName );
		}

		return $links;
	}

	/**
	 * @since 0.4
	 *
	 * @param string $siteId
	 *
	 * @return SimpleSiteLink
	 * @throws OutOfBoundsException
	 */
	public function getSimpleSiteLink( $siteId ) {
		if ( !array_key_exists( $siteId, $this->data['links'] ) ) {
			throw new OutOfBoundsException( "There is no site link with site id '$siteId'" );
		}

		return new SimpleSiteLink( $siteId, $this->data['links'][$siteId] );
	}

	/**
	 * @since 0.4
	 *
	 * @param string $siteId
	 *
	 * @return bool
	 */
	public function hasLinkToSite( $siteId ) {
		return array_key_exists( $siteId, $this->data['links'] );
	}

	/**
	 * @see Entity::isEmpty
	 *
	 * @since 0.1
	 *
	 * @return boolean
	 */
	public function isEmpty() {
		return parent::isEmpty()
			&& $this->data['links'] === array();
	}

	/**
	 * @see Entity::cleanStructure
	 *
	 * @since 0.1
	 *
	 * @param boolean $wipeExisting
	 */
	protected function cleanStructure( $wipeExisting = false ) {
		parent::cleanStructure( $wipeExisting );

		foreach ( array( 'links' ) as $field ) {
			if (  $wipeExisting || !array_key_exists( $field, $this->data ) ) {
				$this->data[$field] = array();
			}
		}
	}

	/**
	 * @see Entity::newFromArray
	 *
	 * @since 0.1
	 *
	 * @param array $data
	 *
	 * @return Item
	 */
	public static function newFromArray( array $data ) {
		return new static( $data );
	}

	/**
	 * @since 0.1
	 *
	 * @return Item
	 */
	public static function newEmpty() {
		return self::newFromArray( array() );
	}

	/**
	 * @see Entity::getType
	 *
	 * @since 0.1
	 *
	 * @return string
	 */
	public function getType() {
		return Item::ENTITY_TYPE;
	}

	/**
	 * @see Entity::newClaimBase
	 *
	 * @since 0.3
	 *
	 * @param Snak $mainSnak
	 *
	 * @return Statement
	 */
	protected function newClaimBase( Snak $mainSnak ) {
		return new Statement( $mainSnak );
	}

	/**
	 * @see Entity::entityToDiffArray
	 *
	 * @since 0.4
	 *
	 * @param Entity $entity
	 *
	 * @return array
	 * @throws InvalidArgumentException
	 */
	protected function entityToDiffArray( Entity $entity ) {
		if ( !( $entity instanceof Item ) ) {
			throw new InvalidArgumentException( 'ItemDiffer only accepts Item objects' );
		}

		$array = parent::entityToDiffArray( $entity );

		$array['links'] = array();

		foreach ( $entity->getSimpleSiteLinks() as $siteLink ) {
			$array['links'][$siteLink->getSiteId()] = $siteLink->getPageName();
		}

		return $array;
	}

	/**
	 * @see Entity::patchSpecificFields
	 *
	 * @since 0.4
	 *
	 * @param EntityDiff $patch
	 * @param Patcher $patcher
	 */
	protected function patchSpecificFields( EntityDiff $patch, Patcher $patcher ) {
		if ( $patch instanceof ItemDiff ) {
			$siteLinksDiff = $patch->getSiteLinkDiff();

			if ( !$siteLinksDiff->isEmpty() ) {
				$links = $this->data['links'];
				$links = $patcher->patch( $links, $siteLinksDiff );
				$this->data['links'] = $links;
			}
		}
	}

	/**
	 * @since 0.5
	 *
	 * @param string $idSerialization
	 *
	 * @return EntityId
	 */
	protected function idFromSerialization( $idSerialization ) {
		return new ItemId( $idSerialization );
	}

}
