<?php

declare( strict_types = 1 );

namespace Wikibase\Repo\Tests\Unit\ServiceWiring;

use Wikibase\Lib\Changes\ChangeStore;
use Wikibase\Lib\Changes\EntityChangeFactory;
use Wikibase\Lib\SettingsArray;
use Wikibase\Repo\Notifications\ChangeNotifier;
use Wikibase\Repo\Store\Store;
use Wikibase\Repo\Tests\Unit\ServiceWiringTestCase;

/**
 * @coversNothing
 *
 * @group Wikibase
 *
 * @license GPL-2.0-or-later
 */
class ChangeNotifierTest extends ServiceWiringTestCase {

	public function testConstructionWithoutChangesTable(): void {
		$this->mockService( 'WikibaseRepo.Settings',
			new SettingsArray( [
				'useChangesTable' => false,
			] ) );
		$this->mockService( 'WikibaseRepo.EntityChangeFactory',
			$this->createMock( EntityChangeFactory::class ) );

		$this->assertInstanceOf(
			ChangeNotifier::class,
			$this->getService( 'WikibaseRepo.ChangeNotifier' )
		);
	}

	public function testConstructionWithChangesTable(): void {
		$this->mockService( 'WikibaseRepo.Settings',
			new SettingsArray( [
				'useChangesTable' => true,
			] ) );
		$store = $this->createMock( Store::class );
		$store->expects( $this->once() )
			->method( 'getChangeStore' )
			->willReturn( $this->createMock( ChangeStore::class ) );
		$this->mockService( 'WikibaseRepo.Store',
			$store );
		$this->mockService( 'WikibaseRepo.EntityChangeFactory',
			$this->createMock( EntityChangeFactory::class ) );

		$this->assertInstanceOf(
			ChangeNotifier::class,
			$this->getService( 'WikibaseRepo.ChangeNotifier' )
		);
	}

}
