<?php declare( strict_types=1 );

namespace Wikibase\Repo\RestApi\Application\UseCases\PatchPropertyDescriptions;

use LogicException;
use Wikibase\DataModel\Term\Term;
use Wikibase\DataModel\Term\TermList;
use Wikibase\Repo\RestApi\Application\UseCases\UseCaseError;
use Wikibase\Repo\RestApi\Application\Validation\DescriptionsSyntaxValidator;
use Wikibase\Repo\RestApi\Application\Validation\LanguageCodeValidator;
use Wikibase\Repo\RestApi\Application\Validation\PropertyDescriptionsContentsValidator;
use Wikibase\Repo\RestApi\Application\Validation\PropertyDescriptionValidator;
use Wikibase\Repo\RestApi\Application\Validation\ValidationError;

/**
 * @license GPL-2.0-or-later
 */
class PatchedPropertyDescriptionsValidator {

	private DescriptionsSyntaxValidator $syntaxValidator;
	private PropertyDescriptionsContentsValidator $contentsValidator;

	public function __construct( DescriptionsSyntaxValidator $syntaxValidator, PropertyDescriptionsContentsValidator $contentsValidator ) {
		$this->syntaxValidator = $syntaxValidator;
		$this->contentsValidator = $contentsValidator;
	}

	/**
	 * @throws UseCaseError
	 */
	public function validateAndDeserialize(
		TermList $originalDescriptions,
		TermList $originalLabels,
		array $descriptionsSerialization
	): TermList {
		$error = $this->syntaxValidator->validate( $descriptionsSerialization ) ?:
			$this->contentsValidator->validate(
				$this->syntaxValidator->getPartiallyValidatedDescriptions(),
				$originalLabels,
				$this->getModifiedLanguages( $originalDescriptions, $this->syntaxValidator->getPartiallyValidatedDescriptions() )
			);
		if ( $error ) {
			$this->throwUseCaseError( $error );
		}

		return $this->contentsValidator->getValidatedDescriptions();
	}

	private function getModifiedLanguages( TermList $original, TermList $modified ): array {
		return array_keys( array_filter(
			iterator_to_array( $modified ),
			fn( Term $description ) => !$original->hasTermForLanguage( $description->getLanguageCode() ) ||
				!$original->getByLanguage( $description->getLanguageCode() )->equals( $description )
		) );
	}

	/**
	 * @return never
	 */
	private function throwUseCaseError( ValidationError $validationError ): void {
		$context = $validationError->getContext();
		switch ( $validationError->getCode() ) {
			case LanguageCodeValidator::CODE_INVALID_LANGUAGE_CODE:
				$languageCode = $validationError->getContext()[LanguageCodeValidator::CONTEXT_LANGUAGE_CODE];
				throw new UseCaseError(
					UseCaseError::PATCHED_DESCRIPTION_INVALID_LANGUAGE_CODE,
					"Not a valid language code '$languageCode' in changed descriptions",
					[ UseCaseError::CONTEXT_LANGUAGE => $languageCode ]
				);
			case DescriptionsSyntaxValidator::CODE_EMPTY_DESCRIPTION:
				$languageCode = $validationError->getContext()[DescriptionsSyntaxValidator::CONTEXT_FIELD_LANGUAGE];
				throw new UseCaseError(
					UseCaseError::PATCHED_DESCRIPTION_EMPTY,
					"Changed description for '$languageCode' cannot be empty",
					[ UseCaseError::CONTEXT_LANGUAGE => $languageCode ]
				);
			case DescriptionsSyntaxValidator::CODE_INVALID_DESCRIPTION_TYPE:
				$language = $context[DescriptionsSyntaxValidator::CONTEXT_FIELD_LANGUAGE];
				$value = json_encode( $context[DescriptionsSyntaxValidator::CONTEXT_FIELD_DESCRIPTION] );
				throw new UseCaseError(
					UseCaseError::PATCHED_DESCRIPTION_INVALID,
					"Changed description for '{$language}' is invalid: {$value}",
					[ UseCaseError::CONTEXT_LANGUAGE => $language, UseCaseError::CONTEXT_VALUE => $value ]
				);
			case PropertyDescriptionValidator::CODE_INVALID:
				$language = $context[PropertyDescriptionValidator::CONTEXT_LANGUAGE];
				$value = $context[PropertyDescriptionValidator::CONTEXT_DESCRIPTION];
				throw new UseCaseError(
					UseCaseError::PATCHED_DESCRIPTION_INVALID,
					"Changed description for '{$language}' is invalid: {$value}",
					[ UseCaseError::CONTEXT_LANGUAGE => $language, UseCaseError::CONTEXT_VALUE => $value ]
				);
			case PropertyDescriptionValidator::CODE_TOO_LONG:
				$languageCode = $context[PropertyDescriptionValidator::CONTEXT_LANGUAGE];
				$maxDescriptionLength = $context[PropertyDescriptionValidator::CONTEXT_LIMIT];
				throw new UseCaseError(
					UseCaseError::PATCHED_DESCRIPTION_TOO_LONG,
					"Changed description for '$languageCode' must not be more than $maxDescriptionLength characters long",
					[
						UseCaseError::CONTEXT_LANGUAGE => $languageCode,
						UseCaseError::CONTEXT_VALUE => $context[ PropertyDescriptionValidator::CONTEXT_DESCRIPTION ],
						UseCaseError::CONTEXT_CHARACTER_LIMIT => $context[ PropertyDescriptionValidator::CONTEXT_LIMIT ],
					]
				);
			case PropertyDescriptionValidator::CODE_LABEL_DESCRIPTION_EQUAL:
				$language = $context[ PropertyDescriptionValidator::CONTEXT_LANGUAGE ];
				throw new UseCaseError(
					UseCaseError::PATCHED_PROPERTY_LABEL_DESCRIPTION_SAME_VALUE,
					"Label and description for language code {$language} can not have the same value.",
					[ UseCaseError::CONTEXT_LANGUAGE => $context[ PropertyDescriptionValidator::CONTEXT_LANGUAGE ] ]
				);
			default:
				throw new LogicException( "Unknown validation error: {$validationError->getCode()}" );
		}
	}

}
