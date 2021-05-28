<?php


	namespace MehrIt\EasyXml\Parse;


	trait StringifiesPaths
	{
		protected $pathDelimiter;

		/**
		 * Returns the default path delimiter
		 * @return string The default path delimiter
		 */
		protected function pathDelimiter() {
			return $this->pathDelimiter !== null ?
				$this->pathDelimiter :
				($this->pathDelimiter = mb_convert_encoding('>', 'UTF-8'));
		}

		/**
		 * Returns a string representation of given path
		 * @param string[] $segments The path segments
		 * @param null|string $delimiter The delimiter. If null, the default path delimiter will be used
		 * @return string The path as string
		 */
		protected function stringifyPath(array $segments, string $delimiter = null) : string {

			if ($delimiter === null)
				$delimiter = $this->pathDelimiter();

			return implode($delimiter, $segments);
		}

	}