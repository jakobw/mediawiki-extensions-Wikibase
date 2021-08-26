<?php
declare( strict_types=1 );

namespace Wikibase\Repo\Tests;

use LogicException;
use PHPUnit\Framework\TestCase;
use Wikibase\DataAccess\EntitySource;
use Wikibase\DataAccess\EntitySourceDefinitions;
use Wikibase\DataAccess\Tests\NewEntitySource;
use Wikibase\Lib\SubEntityTypesMapper;
use Wikibase\Repo\PropertyServices;

/**
 * @covers \Wikibase\Repo\PropertyServices
 *
 * @group Wikibase
 *
 * @license GPL-2.0-or-later
 */
class PropertyServicesTest extends TestCase {

	public function testGetServiceByName(): void {
		$serviceName = 'some-service';
		$definitions = [
			$serviceName => [
				EntitySource::TYPE_API => function () {
					return 'api service';
				},
				EntitySource::TYPE_DB => function () {
					return 'db service';
				},
			]
		];

		$apiSourceName = 'apisource';
		$dbSourceName = 'dbsource';
		$services = new PropertyServices(
			new EntitySourceDefinitions( [
				NewEntitySource::havingName( $apiSourceName )
					->withType( EntitySource::TYPE_API )
					->build(),
				NewEntitySource::havingName( $dbSourceName )->build(),
			], new SubEntityTypesMapper( [] ) ),
			$definitions
		);

		$serviceCallbacksBySource = $services->get( $serviceName );

		$this->assertArrayHasKey( $apiSourceName, $serviceCallbacksBySource );
		$this->assertArrayHasKey( $dbSourceName, $serviceCallbacksBySource );

		$this->assertSame( $serviceCallbacksBySource[$apiSourceName](), 'api service' );
		$this->assertSame( $serviceCallbacksBySource[$dbSourceName](), 'db service' );
	}

	public function testGivenUndefinedServiceName_throws(): void {
		$sourceDefinitions = $this->createStub( EntitySourceDefinitions::class );
		$sourceDefinitions->method( 'getSources' )->willReturn( [] );
		$services = new PropertyServices( $sourceDefinitions, [] );

		$this->expectException( LogicException::class );

		$services->get( 'notaservice' );
	}

}