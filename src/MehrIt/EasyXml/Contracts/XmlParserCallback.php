<?php


	namespace MehrIt\EasyXml\Contracts;


	use MehrIt\EasyXml\Parse\XmlParser;

	interface XmlParserCallback
	{

		/**
		 * Gets the path to call this callback for. Empty if to call for root level elements
		 * @return string[]|null The path to call this callback for or null
		 */
		public function path() : array;

		/**
		 * Returns if the callback should be called for all child elements
		 * @return bool True if to call for child elements. Else false.
		 */
		public function recursive() : bool;

		/**
		 * Gets the node types this callback handles. If null, the callback is invoked for all node types
		 * @return bool[]|null The node types
		 */
		public function types() : ?array;

		/**
		 * Called to invoke the parser callback
		 * @param XmlParser $parser The parser instance
		 */
		public function handle(XmlParser $parser);


		/**
		 * Sets the level the handler is attached to
		 * @param int $level The level
		 * @return XmlParserCallback This instance
		 */
		public function setAttachedLevel(int $level) : XmlParserCallback;

		/**
		 * Gets the level the handler is attached to
		 * @return int The level
		 */
		public function getAttachedLevel() : int;

	}