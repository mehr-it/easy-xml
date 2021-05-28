<?php


	namespace MehrIt\EasyXml\Parse;


	use XMLReader;

	trait NodeTypeNames
	{
		/**
		 * Returns the name name for given XML node type
		 * @param int $type The type
		 * @return string The name
		 */
		protected function nodeTypeName($type) {

			switch ($type) {
				case XMLReader::NONE:
					return 'NONE';
				case XMLReader::ELEMENT:
					return 'ELEMENT';
				case XMLReader::ATTRIBUTE:
					return 'ATTRIBUTE';
				case XMLReader::TEXT:
					return 'TEXT';
				case XMLReader::CDATA:
					return 'CDATA';
				case XMLReader::ENTITY_REF:
					return 'ENTITY_REF';
				case XMLReader::ENTITY:
					return 'ENTITY';
				case XMLReader::PI:
					return 'PI';
				case XMLReader::COMMENT:
					return 'COMMENT';
				case XMLReader::DOC:
					return 'DOC';
				case XMLReader::DOC_TYPE:
					return 'DOC_TYPE';
				case XMLReader::DOC_FRAGMENT:
					return 'DOC_FRAGMENT';
				case XMLReader::NOTATION:
					return 'NOTATION';
				case XMLReader::WHITESPACE:
					return 'WHITESPACE';
				case XMLReader::SIGNIFICANT_WHITESPACE:
					return 'SIGNIFICANT_WHITESPACE';
				case XMLReader::END_ELEMENT:
					return 'END_ELEMENT';
				case XMLReader::END_ENTITY:
					return 'END_ENTITY';
				case XMLReader::XML_DECLARATION:
					return 'XML_DECLARATION';
			}

			return "UNKNOWN_TYPE($type)";
		}
	}