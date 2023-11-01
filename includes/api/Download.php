<?php

use ApiBase;
use ApiFormatRaw;
use ApiMain;
use ApiResult;
use ApiUsageException;
use MediaWiki\Languages\LanguageNameUtils;
use MediaWiki\Page\WikiPageFactory;
use MWException;
use RepoGroup;
use WANObjectCache;
use Wikimedia\ParamValidator\ParamValidator;

use ApiFormatRawFile;

/**
 * Implements file downloads via the action api
 * for consumption by any client, including api-only clients
 * (e. g. via bot password or OAuth)
 * Based on Extension:TimedMediaHandler/includes/ApiTimedText.php
 *
 * @ingroup API
 * @emits error.code timedtext-notfound, invalidlang, invalid-title
 */
class ApiDownload extends ApiBase {

	/** @var RepoGroup */
	private $repoGroup;

	/**
	 * @param ApiMain $main
	 * @param string $action
	 * @param LanguageNameUtils $languageNameUtils
	 * @param RepoGroup $repoGroup
	 * @param WANObjectCache $cache
	 * @param WikiPageFactory $wikiPageFactory
	 */
	public function __construct(
		ApiMain $main,
		$action,
		RepoGroup $repoGroup
	) {
		parent::__construct( $main, $action );
		$this->repoGroup = $repoGroup;
	}

	/**
	 * This module uses a raw printer to directly output files
	 *
	 * @return ApiFormatRaw
	 */
	public function getCustomPrinter(): ApiFormatRawFile {
		$printer = new ApiFormatRawFile( $this->getMain(), null );
		$printer->setFailWithHTTPError( true );
		return $printer;
	}

	/**
	 * @return void
	 * @throws ApiUsageException
	 * @throws MWException
	 */
	public function execute() {
		$params = $this->extractRequestParams();

		$page = $this->getTitleOrPageId( $params );
		if ( !$page->exists() ) {
			$this->dieWithError( 'apierror-missingtitle', 'download-notfound' );
		}

		// Check if we are allowed to read this page
		$this->checkTitleUserPermissions( $page->getTitle(), 'read', [ 'autoblock' => true ] );

		$ns = $page->getTitle()->getNamespace();
		if ( $ns !== NS_FILE ) {
			$this->dieWithError( 'apierror-filedoesnotexist', 'invalidtitle' );
		}
		$file = $this->repoGroup->findFile( $page->getTitle() );
		if ( !$file ) {
			$this->dieWithError( 'apierror-filedoesnotexist', 'download-notfound' );
		}
		if ( !$file->isLocal() ) {
			$this->dieWithError( 'apierror-timedmedia-notlocal', 'download-notlocal' );
		}

		// props like sha
		// $fileProps = $file->getRepo()->getFileProps( $file->getVirtualUrl() );
		// $fileProps = json_encode( ['fileProps' => $fileProps] );

		// get file system path
		$filePath = $file->getRepo()->getLocalReference( $file->getVirtualUrl() )->getPath();

		$mimeType = $file->getMimeType();
		$fileName = $file->getName();

		$result = $this->getResult();
		$result->addValue( null, 'mime', $mimeType, ApiResult::NO_SIZE_CHECK );
		$result->addValue( null, 'filepath', $filePath, ApiResult::NO_SIZE_CHECK );
		$result->addValue( null, 'filename', $fileName, ApiResult::NO_SIZE_CHECK );
		$result->addValue( null, 'size', $file->getSize(), ApiResult::NO_SIZE_CHECK );
	}


	/**
	 * @param int $flags
	 *
	 * @return array
	 */
	public function getAllowedParams( $flags = 0 ) {
		$ret = [
			'title' => [
				ParamValidator::PARAM_TYPE => 'string',
			],
			'pageid' => [
				ParamValidator::PARAM_TYPE => 'integer'
			],
		];
		return $ret;
	}

	/**
	 * @see ApiBase::getExamplesMessages()
	 * @return array of examples
	 */
	protected function getExamplesMessages() {
		return [
			'action=download&title=File:Example.ogv'
				=> 'apihelp-download-example-1',
		];
	}
}
