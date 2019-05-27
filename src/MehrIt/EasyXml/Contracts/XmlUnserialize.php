<?php


	namespace MehrIt\EasyXml\Contracts;

	use MehrIt\EasyXml\Parse\XmlParser;

	interface XmlUnserialize
	{
		/**
		 * Unserializes the object from XML
		 * @param XmlParser $parser The parser
		 */
		public function xmlUnserialize(XmlParser $parser);
	}