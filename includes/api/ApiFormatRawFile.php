<?php

/**
 * Formatter that spits out a raw file of any desired MIME type.
 * Based on ApiFormatRaw
 * @ingroup API
 */
class ApiFormatRawFile extends ApiFormatBase {

	private $errorFallback;
	private $mFailWithHTTPError = false;

	/**
	 * @param ApiMain $main
	 * @param ApiFormatBase|null $errorFallback Object to fall back on for errors
	 */
	public function __construct( ApiMain $main, ApiFormatBase $errorFallback = null ) {
		parent::__construct( $main, 'raw' );
		$this->errorFallback = $errorFallback ?:
			$main->createPrinterByName( $main->getParameter( 'format' ) );
	}

	public function getMimeType() {
		$data = $this->getResult()->getResultData();

		if ( isset( $data['error'] ) || isset( $data['errors'] ) ) {
			return $this->errorFallback->getMimeType();
		}

		if ( !isset( $data['mime'] ) ) {
			ApiBase::dieDebug( __METHOD__, 'No MIME type set for raw formatter' );
		}

		return $data['mime'];
	}

	public function getFilename() {
		$data = $this->getResult()->getResultData();
		if ( isset( $data['error'] ) ) {
			return $this->errorFallback->getFilename();
		} elseif ( !isset( $data['filename'] ) || $this->getIsWrappedHtml() || $this->getIsHtml() ) {
			return parent::getFilename();
		} else {
			return $data['filename'];
		}
	}

	public function initPrinter( $unused = false ) {
		$data = $this->getResult()->getResultData();
		if ( isset( $data['error'] ) || isset( $data['errors'] ) ) {
			$this->errorFallback->initPrinter( $unused );
			if ( $this->mFailWithHTTPError ) {
				$this->getMain()->getRequest()->response()->statusHeader( 400 );
			}
		} else {
			parent::initPrinter( $unused );
		}
	}

	public function closePrinter() {
		$data = $this->getResult()->getResultData();
		if ( isset( $data['error'] ) || isset( $data['errors'] ) ) {
			$this->errorFallback->closePrinter();
		} else {
			parent::closePrinter();
		}
	}

	public function execute() {
		$data = $this->getResult()->getResultData();
		if ( isset( $data['error'] ) || isset( $data['errors'] ) ) {
			$this->errorFallback->execute();
			return;
		}

		if ( !isset( $data['filepath'] ) ) {
			ApiBase::dieDebug( __METHOD__, 'No file system path given for raw file format' );
		}

		$file_path = $data['filepath'];
		$file_name = $data['filename'];

		if ( ! file_exists ($file_path) ) {
			ApiBase::dieDebug( __METHOD__, "$file_path not found" );
		}
		  
		$mimeType = $data['mime'];
		$fileSize = $data['size'];
		  
		// set response headers
		header( "Content-Type: $mimeType" );
		header( "Content-Length: $fileSize" );
		header( "Content-Disposition: attachment; filename=$file_name" );
		header( 'Expires: 0' );
		header( 'Cache-Control: must-revalidate' );
		header( 'Pragma: public' );

		// Write the file stream
		readfile($file_path);

		exit; //needed but not sure if this is a good idea here
	}

    /**
	 * Output HTTP error code 400 when if an error is encountered
	 *
	 * The purpose is for output formats where the user-agent will
	 * not be able to interpret the validity of the content in any
	 * other way. For example subtitle files read by browser video players.
	 *
	 * @param bool $fail
	 */
	public function setFailWithHTTPError( $fail ) {
		$this->mFailWithHTTPError = $fail;
	}
}
