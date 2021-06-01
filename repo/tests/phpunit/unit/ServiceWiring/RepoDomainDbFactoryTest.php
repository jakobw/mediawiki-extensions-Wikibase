<?php

declare( strict_types = 1 );

namespace Wikibase\Repo\Tests\Unit\ServiceWiring;

use Wikibase\DataAccess\EntitySourceDefinitions;
use Wikibase\Lib\Rdbms\RepoDomainDbFactory;
use Wikibase\Repo\Tests\Unit\ServiceWiringTestCase;

/**
 * @coversNothing
 *
 * @group Wikibase
 *
 * @license GPL-2.0-or-later
 */
class RepoDomainDbFactoryTest extends ServiceWiringTestCase {

	public function testConstruction() {
		$this->mockService(
			'WikibaseRepo.EntitySourceDefinitions',
			$this->createStub( EntitySourceDefinitions::class )
		);

		$this->assertInstanceOf(
			RepoDomainDbFactory::class,
			$this->getService( 'WikibaseRepo.RepoDomainDbFactory' )
		);
	}

}
