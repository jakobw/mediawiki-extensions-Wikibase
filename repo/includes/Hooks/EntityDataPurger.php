<?php

declare( strict_types = 1 );

namespace Wikibase\Repo\Hooks;

use HtmlCacheUpdater;
use JobQueueGroup;
use JobSpecification;
use MediaWiki\Hook\ArticleRevisionVisibilitySetHook;
use MediaWiki\Page\Hook\ArticleDeleteCompleteHook;
use Title;
use Wikibase\Lib\Store\EntityIdLookup;
use Wikibase\Repo\LinkedData\EntityDataUriManager;
use Wikibase\Repo\WikibaseRepo;

/**
 * @license GPL-2.0-or-later
 */
class EntityDataPurger implements ArticleRevisionVisibilitySetHook, ArticleDeleteCompleteHook {

	/** @var EntityIdLookup */
	private $entityIdLookup;

	/** @var EntityDataUriManager */
	private $entityDataUriManager;

	/** @var HtmlCacheUpdater */
	private $htmlCacheUpdater;

	/** @var callable */
	private $jobQueueGroupFactory;

	public function __construct(
		EntityIdLookup $entityIdLookup,
		EntityDataUriManager $entityDataUriManager,
		HtmlCacheUpdater $htmlCacheUpdater,
		callable $jobQueueGroupFactory
	) {
		$this->entityIdLookup = $entityIdLookup;
		$this->entityDataUriManager = $entityDataUriManager;
		$this->htmlCacheUpdater = $htmlCacheUpdater;
		$this->jobQueueGroupFactory = $jobQueueGroupFactory;
	}

	public static function factory( HtmlCacheUpdater $htmlCacheUpdater ): self {
		$wikibaseRepo = WikibaseRepo::getDefaultInstance();
		return new self(
			$wikibaseRepo->getEntityIdLookup(),
			$wikibaseRepo->getEntityDataUriManager(),
			$htmlCacheUpdater,
			'JobQueueGroup::singleton'
		);
	}

	/**
	 * @param Title $title
	 * @param int[] $ids
	 * @param int[][] $visibilityChangeMap
	 */
   public function onArticleRevisionVisibilitySet( $title, $ids, $visibilityChangeMap ): void {
		$entityId = $this->entityIdLookup->getEntityIdForTitle( $title );
		if ( !$entityId ) {
			return;
		}

		$urls = [];
		foreach ( $ids as $revisionId ) {
			$urls = array_merge( $urls, $this->entityDataUriManager->getPotentiallyCachedUrls(
				$entityId,
				// $ids should be int[] but MediaWiki may call with a string[], so cast to int
				(int)$revisionId
			) );
		}
		if ( $urls !== [] ) {
			$this->htmlCacheUpdater->purgeUrls( $urls );
		}
   }

	/**
	 * @param \WikiPage $wikiPage
	 * @param \User $user
	 * @param string $reason
	 * @param int $id
	 * @param \Content|null $content
	 * @param \ManualLogEntry $logEntry
	 * @param int $archivedRevisionCount
	 * @return bool|void
	 */
	public function onArticleDeleteComplete(
		$wikiPage,
		$user,
		$reason,
		$id,
		$content,
		$logEntry,
		$archivedRevisionCount
	) {
		$title = $wikiPage->getTitle();
		$entityId = $this->entityIdLookup->getEntityIdForTitle( $title );
		if ( !$entityId ) {
			return;
		}

		/** @var JobQueueGroup $jobQueueGroup */
		$jobQueueGroup = ( $this->jobQueueGroupFactory )();
		'@phan-var JobQueueGroup $jobQueueGroup';
		$jobQueueGroup->lazyPush( new JobSpecification( 'PurgeEntityData', [
			'namespace' => $title->getNamespace(),
			'title' => $title->getDBkey(),
			'pageId' => $id,
			'entityId' => $entityId->getSerialization(),
		] ) );
	}
}
