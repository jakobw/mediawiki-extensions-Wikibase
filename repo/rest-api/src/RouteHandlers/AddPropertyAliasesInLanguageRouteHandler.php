<?php declare( strict_types=1 );

namespace Wikibase\Repo\RestApi\RouteHandlers;

use MediaWiki\MediaWikiServices;
use MediaWiki\Rest\Handler;
use MediaWiki\Rest\RequestInterface;
use MediaWiki\Rest\Response;
use MediaWiki\Rest\ResponseInterface;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\Rest\StringStream;
use Wikibase\Repo\RestApi\Application\UseCases\AddPropertyAliasesInLanguage\AddPropertyAliasesInLanguage;
use Wikibase\Repo\RestApi\Application\UseCases\AddPropertyAliasesInLanguage\AddPropertyAliasesInLanguageRequest;
use Wikibase\Repo\RestApi\Application\UseCases\AddPropertyAliasesInLanguage\AddPropertyAliasesInLanguageResponse;
use Wikibase\Repo\RestApi\Application\UseCases\UseCaseError;
use Wikibase\Repo\RestApi\RouteHandlers\Middleware\AuthenticationMiddleware;
use Wikibase\Repo\RestApi\RouteHandlers\Middleware\BotRightCheckMiddleware;
use Wikibase\Repo\RestApi\RouteHandlers\Middleware\MiddlewareHandler;
use Wikibase\Repo\RestApi\RouteHandlers\Middleware\UserAgentCheckMiddleware;
use Wikibase\Repo\RestApi\WbRestApi;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * @license GPL-2.0-or-later
 */
class AddPropertyAliasesInLanguageRouteHandler extends SimpleHandler {

	use AssertContentType;
	use AssertValidTopLevelFields;

	private const PROPERTY_ID_PATH_PARAM = 'property_id';
	private const LANGUAGE_CODE_PATH_PARAM = 'language_code';
	private const ALIASES_BODY_PARAM = 'aliases';

	private const TAGS_BODY_PARAM = 'tags';
	private const BOT_BODY_PARAM = 'bot';
	private const COMMENT_BODY_PARAM = 'comment';

	private AddPropertyAliasesInLanguage $addPropertyAliases;
	private MiddlewareHandler $middlewareHandler;
	private ResponseFactory $responseFactory;

	public function __construct(
		AddPropertyAliasesInLanguage $addPropertyAliases,
		MiddlewareHandler $middlewareHandler,
		ResponseFactory $responseFactory
	) {
		$this->addPropertyAliases = $addPropertyAliases;
		$this->middlewareHandler = $middlewareHandler;
		$this->responseFactory = $responseFactory;
	}

	public static function factory(): Handler {
		$responseFactory = new ResponseFactory();

		return new self(
			WbRestApi::getAddPropertyAliasesInLanguage(),
			new MiddlewareHandler( [
				WbRestApi::getUnexpectedErrorHandlerMiddleware(),
				new UserAgentCheckMiddleware(),
				new AuthenticationMiddleware(),
				new BotRightCheckMiddleware( MediaWikiServices::getInstance()->getPermissionManager(), $responseFactory ),
				WbRestApi::getPreconditionMiddlewareFactory()->newPreconditionMiddleware(
					fn( RequestInterface $request ): string => $request->getPathParam( self::PROPERTY_ID_PATH_PARAM )
				),
			] ),
			$responseFactory
		);
	}

	/**
	 * @param mixed ...$args
	 */
	public function run( ...$args ): Response {
		return $this->middlewareHandler->run( $this, [ $this, 'runUseCase' ], $args );
	}

	public function runUseCase( string $propertyId, string $languageCode ): Response {
		$jsonBody = $this->getValidatedBody();
		'@phan-var array $jsonBody'; // guaranteed to be an array per parseBodyData()

		try {
			$useCaseResponse = $this->addPropertyAliases->execute(
				new AddPropertyAliasesInLanguageRequest(
					$propertyId,
					$languageCode,
					$jsonBody[self::ALIASES_BODY_PARAM],
					$jsonBody[self::TAGS_BODY_PARAM],
					$jsonBody[self::BOT_BODY_PARAM],
					$jsonBody[self::COMMENT_BODY_PARAM],
					$this->getUsername()
				)
			);
		} catch ( UseCaseError $error ) {
			return $this->responseFactory->newErrorResponseFromException( $error );
		}

		return $this->newSuccessHttpResponse( $useCaseResponse );
	}

	public function parseBodyData( RequestInterface $request ): ?array {
		$this->assertContentType( [ 'application/json' ], $request->getBodyType() ?? 'unknown' );
		$body = parent::parseBodyData( $request );
		$this->assertValidTopLevelTypes( $body, $this->getBodyParamSettings() );

		return $body;
	}

	public function getParamSettings(): array {
		return [
			self::PROPERTY_ID_PATH_PARAM => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
			self::LANGUAGE_CODE_PATH_PARAM => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
			self::ALIASES_BODY_PARAM => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'array',
				ParamValidator::PARAM_REQUIRED => true,
			],
			self::TAGS_BODY_PARAM => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'array',
				ParamValidator::PARAM_REQUIRED => false,
				ParamValidator::PARAM_DEFAULT => [],
			],
			self::BOT_BODY_PARAM => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'boolean',
				ParamValidator::PARAM_REQUIRED => false,
				ParamValidator::PARAM_DEFAULT => false,
			],
			self::COMMENT_BODY_PARAM => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => false,
			],
		];
	}

	private function newSuccessHttpResponse( AddPropertyAliasesInLanguageResponse $useCaseResponse ): Response {
		$httpResponse = $this->getResponseFactory()->create();
		$httpResponse->setStatus( $useCaseResponse->wasAddedToExistingAliasGroup() ? 200 : 201 );
		$httpResponse->setHeader( 'Content-Type', 'application/json' );
		$httpResponse->setHeader(
			'Last-Modified',
			wfTimestamp( TS_RFC2822, $useCaseResponse->getLastModified() )
		);
		$this->setEtagFromRevId( $httpResponse, $useCaseResponse->getRevisionId() );
		$httpResponse->setBody( new StringStream( json_encode( $useCaseResponse->getAliases()->getAliases() ) ) );

		return $httpResponse;
	}

	private function setEtagFromRevId( Response $httpResponse, int $revId ): void {
		$httpResponse->setHeader( 'ETag', "\"$revId\"" );
	}

	/**
	 * Preconditions are checked via {@link PreconditionMiddleware}
	 */
	public function checkPreconditions(): ?ResponseInterface {
		return null;
	}

	private function getUsername(): ?string {
		$mwUser = $this->getAuthority()->getUser();
		return $mwUser->isRegistered() ? $mwUser->getName() : null;
	}

}
