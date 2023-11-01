<?php
/**
 * Copyright © 2015 Derk-Jan Hartman "hartman.wiki@gmail.com"
 * Updated 2017-2019 Brion Vibber <bvibber@wikimedia.org>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 * @since 1.33
 */

use ApiBase;
use ApiFormatRaw;
use ApiMain;
use ApiResult;
use ApiUsageException;
use File;
use MediaWiki\Languages\LanguageNameUtils;
use MediaWiki\Page\WikiPageFactory;
use MWException;
use RepoGroup;
use TextContent;
use Title;
use WANObjectCache;
use Wikimedia\ParamValidator\ParamValidator;
use WikiPage;

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
	/** @var LanguageNameUtils */
	private $languageNameUtils;

	/** @var RepoGroup */
	private $repoGroup;

	/** @var WANObjectCache */
	private $cache;

	/** @var WikiPageFactory */
	private $wikiPageFactory;

	/** @var int version of the cache format */
	private const CACHE_VERSION = 1;

	/** @var int default 24 hours */
	private const CACHE_TTL = 86400;

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
		LanguageNameUtils $languageNameUtils,
		RepoGroup $repoGroup,
		WANObjectCache $cache,
		WikiPageFactory $wikiPageFactory
	) {
		parent::__construct( $main, $action );
		$this->languageNameUtils = $languageNameUtils;
		$this->repoGroup = $repoGroup;
		$this->cache = $cache;
		$this->wikiPageFactory = $wikiPageFactory;
	}

	/**
	 * This module uses a raw printer to directly output SRT, VTT or other subtitle formats
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
		

		// We want to cache our output
		/* $this->getMain()->setCacheMode( 'public' );
		if ( !$this->getMain()->getParameter( 'smaxage' ) ) {
			// cache at least 15 seconds.
			$this->getMain()->setCacheMaxAge( 15 );
		}*/

		$mimeType = $file->getMimeType();
		$fileName = $file->getName();
			// Unreachable due to parameter validation,
			// unless someone adds a new format and forgets. :D
			//throw new MWException( 'Unsupported timedtext trackformat' );

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
