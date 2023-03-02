<?php declare( strict_types=1 );

namespace Wikibase\Repo\Tests\RestApi\Domain\ReadModel;

use PHPUnit\Framework\TestCase;
use Wikibase\Repo\RestApi\Domain\ReadModel\Aliases;
use Wikibase\Repo\RestApi\Domain\ReadModel\AliasesInLanguage;

/**
 * @covers \Wikibase\Repo\RestApi\Domain\ReadModel\Aliases
 *
 * @group Wikibase
 *
 * @license GPL-2.0-or-later
 */
class AliasesTest extends TestCase {

	public function testConstructor(): void {
		$enAliases = new AliasesInLanguage( 'en', [ 'Douglas Noël Adams', 'DNA' ] );
		$deAliases = new AliasesInLanguage( 'de', [ 'Douglas Noël Adams' ] );
		$aliases = new Aliases( $enAliases, $deAliases );

		$this->assertSame( $enAliases, $aliases['en'] );
		$this->assertSame( $deAliases, $aliases['de'] );
	}

}
