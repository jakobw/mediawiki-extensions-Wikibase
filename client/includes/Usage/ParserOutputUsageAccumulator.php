<?php

declare( strict_types = 1 );

namespace Wikibase\Client\Usage;

use MediaWiki\Parser\ParserOutput;

/**
 * This implementation of the UsageAccumulator interface acts as a wrapper around
 * a ParserOutput object. Thus, this class encapsulates the knowledge about how usage
 * is tracked in the ParserOutput.
 *
 * @license GPL-2.0-or-later
 * @author Daniel Kinzler
 */
class ParserOutputUsageAccumulator extends ParserUsageAccumulator {

	/**
	 * Key used to store data in ParserOutput.  Exported for use by unit tests.
	 * @var string
	 */
	public const EXTENSION_DATA_KEY = 'wikibase-entity-usage';

	private EntityUsageFactory $entityUsageFactory;
	private UsageDeduplicator $usageDeduplicator;

	public function __construct(
		ParserOutput $parserOutput,
		EntityUsageFactory $entityUsageFactory,
		UsageDeduplicator $deduplicator
	) {
		parent::__construct( $parserOutput );
		$this->usageDeduplicator = $deduplicator;
		$this->entityUsageFactory = $entityUsageFactory;
	}

	/**
	 * @see UsageAccumulator::addUsage
	 */
	public function addUsage( EntityUsage $usage ): void {
		$this->getParserOutput()->appendExtensionData(
			self::EXTENSION_DATA_KEY, $usage->getIdentityString()
		);
	}

	/**
	 * @see UsageAccumulator::getUsage
	 *
	 * @return EntityUsage[]
	 */
	public function getUsages(): array {
		$usageIdentities = $this->getParserOutput()->getExtensionData( self::EXTENSION_DATA_KEY ) ?: [];

		$usages = [];
		foreach ( $usageIdentities as $usageIdentity => $value ) {
			$usages[] = $this->entityUsageFactory->newFromIdentity( $usageIdentity );
		}

		if ( $usages ) {
			return $this->usageDeduplicator->deduplicate( $usages );
		}
		return [];
	}
}
