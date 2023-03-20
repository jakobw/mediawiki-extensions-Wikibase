<?php declare( strict_types=1 );

namespace Wikibase\Repo\Tests\RestApi\UseCases\GetItemLabel;

use PHPUnit\Framework\TestCase;
use Wikibase\Repo\RestApi\UseCases\GetItemLabel\GetItemLabelRequest;
use Wikibase\Repo\RestApi\UseCases\GetItemLabel\GetItemLabelValidator;
use Wikibase\Repo\RestApi\UseCases\UseCaseException;
use Wikibase\Repo\RestApi\Validation\ItemIdValidator;
use Wikibase\Repo\RestApi\Validation\LanguageCodeValidator;
use Wikibase\Repo\WikibaseRepo;

/**
 * @covers \Wikibase\Repo\RestApi\UseCases\GetItemLabels\GetItemLabelsValidator
 *
 * @group Wikibase
 *
 * @license GPL-2.0-or-later
 */
class GetItemLabelValidatorTest extends TestCase {

	/**
	 * @doesNotPerformAssertions
	 */
	public function testWithValidIdAndLanguageCode(): void {
		$this->newLabelValidator()
			->assertValidRequest( new GetItemLabelRequest( 'Q321', 'en' ) );
	}

	public function testWithInvalidId(): void {
		$invalidId = 'X123';

		try {
			$this->newLabelValidator()
				->assertValidRequest( new GetItemLabelRequest( $invalidId, 'en' ) );

			$this->fail( 'this should not be reached' );
		} catch ( UseCaseException $useCaseEx ) {
			$this->assertSame( ItemIdValidator::CODE_INVALID, $useCaseEx->getErrorCode() );
			$this->assertSame( 'Not a valid item ID: ' . $invalidId, $useCaseEx->getErrorMessage() );
		}
	}

	public function testWithInvalidLanguageCode(): void {
		$invalidLanguageCode = '1e';

		try {
			$this->newLabelValidator()
				->assertValidRequest( new GetItemLabelRequest( 'Q123', $invalidLanguageCode ) );

			$this->fail( 'this should not be reached' );
		} catch ( UseCaseException $useCaseEx ) {
			$this->assertSame( LanguageCodeValidator::CODE_INVALID_LANGUAGE_CODE, $useCaseEx->getErrorCode() );
			$this->assertSame( 'Not a valid language code: ' . $invalidLanguageCode, $useCaseEx->getErrorMessage() );
		}
	}

	private function newLabelValidator(): GetItemLabelValidator {
		return ( new GetItemLabelValidator(
			new ItemIdValidator(),
			new LanguageCodeValidator( WikibaseRepo::getTermsLanguages()->getLanguages() )
		) );
	}

}
