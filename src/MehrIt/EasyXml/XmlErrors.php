<?php


	namespace MehrIt\EasyXml;


	use MehrIt\EasyXml\Exception\XmlException;

	trait XmlErrors
	{

		/**
		 * Activates the internal XML errors during execution of the given callback
		 * @param callable $callback The callback
		 * @return mixed The callback return
		 */
		protected function withXmlErrors(callable $callback) {

			$previous = libxml_use_internal_errors(true);

			try {
				return call_user_func($callback);
			}
			finally {
				libxml_use_internal_errors($previous);
			}
		}

		/**
		 * Throws the last LibXMLError as exception, if the given value is false
		 * @param mixed $value The value
		 * @return mixed The value
		 * @throws XmlException
		 */
		protected function e($value) {

			if ($value === false) {
				$lastError = libxml_get_last_error() ?: null;

				libxml_clear_errors();

				throw new XmlException($lastError);
			}
			else {
				return $value;
			}

		}

		/**
		 * Creates a custom error handler for the execution of the callback which throws XMl exceptions from given errors
		 * @param callable $callback The callback
		 * @return mixed The callback return
		 */
		protected function withXmlErrorHandler(callable $callback) {

			set_error_handler(function (int $errorNumber, string $errorString) {

				$errorString = trim(preg_replace('/XMLWriter::.*?\(\):/', '', $errorString));

				throw new XmlException(null, $errorString, $errorNumber);

			}, E_ALL | E_STRICT);
			try {
				return call_user_func($callback);
			}
			finally {
				restore_error_handler();
			}
		}
	}