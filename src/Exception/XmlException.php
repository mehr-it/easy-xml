<?php
	/**
	 * Created by PhpStorm.
	 * User: chris
	 * Date: 05.03.19
	 * Time: 09:24
	 */

	namespace MehrIt\EasyXml\Exception;


	use LibXMLError;
	use RuntimeException;
	use Throwable;

	class XmlException extends RuntimeException
	{

		/** @var LibXMLError|null */
		protected $xmlError;

		/**
		 * @inheritDoc
		 */
		public function __construct(?LibXMLError $xmlError, $message = "", $code = 0, Throwable $previous = null) {

			$this->xmlError = $xmlError;

			if (!$message) {
				if ($xmlError) {
					$filename = $xmlError->file;
					if ($filename && substr($filename, 0, 10) == 'wrapper://')
						$filename = null;

					$message = trim(($xmlError->message ?: 'Unspecified XML error')) . ' at ' . ($filename ?
							$filename . ':' . $xmlError->line . ($xmlError->column ? ',' . $xmlError->column : '') . ')' :
							'line ' . $xmlError->line . ($xmlError->column ? ',' . $xmlError->column : '')
						);
				}
				else {
					$message = 'An error processing XML occurred';
				}
			}

			parent::__construct($message, $code, $previous);
		}

		/**
		 * Gets the LibXMLError
		 * @return LibXMLError|null
		 */
		public function getXmlError(): ?LibXMLError {
			return $this->xmlError;
		}

		/**
		 * Gets the XML error level
		 * @return int|null The error level (LIBXML_ERR_WARNING, LIBXML_ERR_ERROR or LIBXML_ERR_FATAL)
		 */
		public function getXmlErrorLevel() : ?int{

			if (!$this->xmlError)
				return null;

			return $this->xmlError->level;
		}

		/**
		 * The XML error code (http://www.xmlsoft.org/html/libxml-xmlerror.html)
		 * @return int|null The error code
		 */
		public function getXmlErrorCode() : ?int {
			if (!$this->xmlError)
				return null;

			return $this->xmlError->code;
		}


	}