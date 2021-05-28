<?php


	namespace MehrIt\EasyXml\Parse;


	use Closure;
	use InvalidArgumentException;
	use MehrIt\EasyXml\Contracts\XmlParserCallback;
	use MehrIt\EasyXml\Contracts\XmlUnserialize;
	use MehrIt\EasyXml\Exception\XmlException;
	use MehrIt\EasyXml\Parse\Callbacks\CurrentElementValueCallback;
	use MehrIt\EasyXml\Parse\Callbacks\ElementStartCallback;
	use MehrIt\EasyXml\Parse\Callbacks\ElementValueCallback;
	use MehrIt\EasyXml\Stream\StreamWrapper;
	use MehrIt\EasyXml\XmlErrors;
	use RuntimeException;
	use XMLReader;


	/**
	 * Parser for XML files (using XMLReader internally)
	 * @package MehrIt\EasyXml\Parse
	 */
	class XmlParser
	{
		use NodeTypeNames;
		use StringifiesPaths;
		use ConvertsValues;
		use XmlErrors;

		/**
		 * @var XMLReader
		 */
		protected $reader;

		protected $handlers = [];

		protected $pathSegments = [];

		/**
		 * @var string
		 */
		protected $pathMatchStr;

		protected $depth = 0;

		protected $elementId = 0;

		protected $currentlyHandledElementId = [0];

		protected $rootParsed = false;

		/**
		 * @var callable[]
		 */
		protected $endCallbacks = [];

		/**
		 * @var XmlParserCallback[][]
		 */
		protected $callbacks = [];

		/**
		 * @var XmlParserCallback[][]
		 */
		protected $activeCallbacks = [];

		/**
		 * @var boolean[]
		 */
		protected $subtreeCallbacks = [];

		/**
		 * @var string[]
		 */
		protected $xmlnsStack = [];

		/**
		 * @var string[]
		 */
		protected $prefixes = [];


		/**
		 * @var string The output encoding
		 */
		protected $outputEncoding;

		/**
		 * Creates a new XML reader instance parsing XML from given string
		 * @param string $xml The XML
		 * @param string $tempUri The URI for creating a temporary stream
		 * @return XmlParser The parser instance
		 */
		public static function fromString(string $xml, string $tempUri = 'php://memory') {

			// we create a temporary stream, write XML to it and rewind
			$res = fopen($tempUri, 'w+');
			if (fwrite($res, $xml) === false)
				throw new RuntimeException("Could not write XML to temporary \"$tempUri\"");
			if (!rewind($res))
				throw new RuntimeException("Could not rewind temporary XML stream \"$tempUri\"");

			return new static($res);
		}

		/**
		 * Creates a new instance
		 * @param XMLReader|resource|string $source The source. Either an XMl reader instance, a resource to read from or an URI as string
		 */
		public function __construct($source) {

			if ($source instanceof XMLReader) {
				$this->reader = $source;
			}
			else {
				$reader = $this->reader = new XMLReader();

				if (is_resource($source)) {
					$id = StreamWrapper::register($source);
					$this->e($reader->open('wrapper://' . $id));
				}
				else {
					$this->e($reader->open($source));
				}
			}

			$this->outputEncoding = mb_internal_encoding();
		}

		/**
		 * Gets the current output encoding
		 * @return string The current output encoding
		 */
		public function getOutputEncoding() : string {
			return $this->outputEncoding;
		}

		/**
		 * Gets the output encoding
		 * @param string $outputEncoding The output encoding
		 * @return XmlParser
		 */
		public function setOutputEncoding(string $outputEncoding) {
			$this->outputEncoding = $outputEncoding;

			return $this;
		}

		/**
		 * Sets a namespace prefix to a given URI. The given namespace prefix may be used in paths to reference the namespace with given URI. If a prefix is already used with another URL an exception is thrown
		 * @param string $name The prefix name
		 * @param string $uri The namespace URI
		 * @return $this
		 * @throws InvalidArgumentException
		 */
		public function prefix(string $name, string $uri) {

			$name = mb_convert_encoding($name, 'UTF-8');
			$uri  = mb_convert_encoding($uri, 'UTF-8');

			if (($this->prefixes[$name] ?? null) !== null && $this->prefixes[$name] !== $uri)
				throw new InvalidArgumentException("URI for XML prefix \"$name\" already set");

			$this->prefixes[$name] = $uri;

			return $this;
		}

		/**
		 * Gets the name of the current element the parser points to
		 * @return string The element name
		 */
		public function elName() : string {
			$this->assertParsingNotContinued();

			return $this->decodeString($this->reader->localName);
		}

		/**
		 * Gets the namespace URI of the current element the parser points to
		 * @return string|null The namespace URI if within namespace. Else null
		 */
		public function elNamespaceUri() : ?string {
			$this->assertParsingNotContinued();

			$uri = $this->reader->namespaceURI;

			if ($uri === '')
				return null;

			return $this->decodeString($uri);
		}

		/**
		 * Returns if the current element the parser points to has attributes
		 * @return bool True if has attributes. Else false.
		 */
		public function elHasAttributes(): bool {
			$this->assertParsingNotContinued();

			return $this->reader->hasAttributes;
		}

		/**
		 * Returns if the current element the parser points to has an attribute with given name
		 * @param string $name The attribute name
		 * @return bool True if has attribute. Else false.
		 */
		public function elHasAttribute(string $name) {
			$this->assertParsingNotContinued();

			return $this->elAttribute($name) !== null;
		}

		/**
		 * Gets the value of the attribute with given name of the current element the parser points to
		 * @param string $name The name
		 * @param mixed|null $default The default value if attribute does not exist
		 * @return string|null|mixed The attribute value or the given default value if attribute does not exist
		 */
		public function elAttribute(string $name, $default = null) {
			$this->assertParsingNotContinued();

			[$ns, $name] = $this->parseAttributeName($name);

			if ($ns === null)
				$value = $this->reader->getAttribute($name);
			else
				$value = $this->reader->getAttributeNs($name, $ns);

			if ($value === null)
				return $default;

			return $this->decodeString($value);
		}

		/**
		 * Invokes the given callback for each attribute of current element
		 * @param callable $callback The callback. Will receive attribute name, attribute value and attribute namespace URI as parameters
		 * @return XmlParser This instance
		 */
		public function elAttributesEach(callable $callback) {
			$this->assertParsingNotContinued();

			$reader = $this->reader;

			if ($reader->nodeType == XMLReader::ELEMENT) {

				$attrCount = $reader->attributeCount;
				if ($attrCount > 0) {
					try {
						for ($i = 0; $i < $attrCount; ++$i) {
							$this->e($reader->moveToAttributeNo($i));

							$ns = $reader->namespaceURI;

							call_user_func(
								$callback,
								$this->decodeString($reader->localName),
								$this->decodeString($reader->value),
								$this->decodeString($ns !== '' ? $ns : null)
							);
						}
					}
					finally {
						$this->e($reader->moveToElement());
					}
				}
			}

			return $this;
		}

		/**
		 * Returns if the current element the parser points to is self closing
		 * @return bool True if is self closing. Else false.
		 */
		public function elSelfClosing(): bool {
			$this->assertParsingNotContinued();

			$reader = $this->reader;

			return $reader->nodeType === XMLReader::ELEMENT && $reader->isEmptyElement;
		}

		/**
		 * Returns if the current element the parser points to is an end element or is self closing
		 * @return bool True if is end element or self closing
		 */
		public function elClosing() : bool {
			$this->assertParsingNotContinued();

			return $this->reader->nodeType === XMLReader::END_ELEMENT || $this->elSelfClosing();
		}

		/**
		 * Gets the type of the current element the parser points to
		 * @return int The element type
		 */
		public function elType() : int {
			$this->assertParsingNotContinued();

			return $this->reader->nodeType;
		}

		/**
		 * Gets the text value of the current element the parser points to
		 * @return string|null The text value
		 */
		public function elValue() : ?string {
			$this->assertParsingNotContinued();

			return $this->decodeString($this->reader->value);
		}

		/**
		 * Adds a callback to be called when current element has completely been parsed
		 * @param callable $callback The callback
		 * @return XmlParser This instance
		 */
		public function elEnd(callable $callback) {

			$this->assertParsingNotContinued();

			if ($this->reader->nodeType !== XMLReader::ELEMENT)
				throw new RuntimeException('Cannot add element end callback for element of type ' . $this->nodeTypeName($this->reader->nodeType));

			$this->endCallbacks[$this->depth][] = $callback;

			return $this;
		}

		/**
		 * Parses the document
		 * @return XmlParser
		 */
		public function parse(): XmlParser {

			if ($this->elementId > 0)
				throw new RuntimeException('Parsing already started');

			$this->depth        = 0;
			$this->pathSegments = [];
			$this->pathMatchStr = $this->pathDelimiter();

			$this->withXmlErrors(function() {
				while ($this->next()) {
					$this->handle();
				}
			});

			return $this;
		}

		/**
		 * Parses all inner content of the current element and stops after element end
		 * @return XmlParser
		 */
		public function consume() : XmlParser {

			$this->assertParsingNotContinued();

			$reader = $this->reader;
			if ($this->depth <= 0)
				throw new RuntimeException('Cannot consume when no element opened');

			if ($reader->nodeType !== XMLReader::ELEMENT)
				throw new RuntimeException('Cannot consume elements of type ' . $this->nodeTypeName($reader->nodeType));

			// for empty elements, there is nothing inner content to be parsed, so stop here
			if ($reader->isEmptyElement)
				return $this;

			$startDepth = $this->depth;

			$stop = false;
			do {
				// read next
				if (!$this->next())
					throw new XmlException(null, 'Unexpected end of XML document');

				// stop parsing after this element, when our starting depth is reached and a closing element was read
				if ($this->depth === $startDepth && $reader->nodeType === XMLReader::END_ELEMENT)
					$stop = true;

				// handle element
				$this->handle();

			} while (!$stop);



			return $this;
		}

		/**
		 * Invokes the given callback when parser points to root node
		 * @param callable $handler The callback. Will receive this parser instance as argument
		 * @return XmlParser This instance
		 */
		public function root(callable $handler) {
			return $this->addCallback(new ElementStartCallback(['>'], $handler));
		}

		/**
		 * Invokes the given callback when parser points to element matching given path
		 * @param string $path The path (relative to current element parser points to)
		 * @param callable $handler The callback. Will receive this parser instance as argument
		 * @param string $delimiter The path delimiter used
		 * @return XmlParser This instance
		 */
		public function each(string $path, callable $handler, string $delimiter = '.') {
			return $this->addCallback(new ElementStartCallback($this->parseElementPath($path, $delimiter), $handler));
		}

		/**
		 * Invokes the given callback when parser points to the first element matching given path
		 * @param string $path The path (relative to current element parser points to)
		 * @param callable $handler The callback. Will receive this parser instance as argument
		 * @param string $delimiter The path delimiter used
		 * @return XmlParser This instance
		 */
		public function first(string $path, callable $handler, string $delimiter = '.') {

			$parsed = false;

			return $this->each($path, function(XmlParser $parser) use (&$parsed, $handler) {

				if (!$parsed) {
					call_user_func($handler, $parser);

					$parsed = true;
				}

			}, $delimiter);
		}

		/**
		 * Invokes the given callback with element value when parsing of element matching given path is complete
		 * @param string $path The path (relative to current element parser points to)
		 * @param callable $handler The callback. Will receive the element value as argument
		 * @param string $delimiter The path delimiter used
		 * @return XmlParser This instance
		 */
		public function eachValue(string $path, callable $handler, string $delimiter = '.') {
			return $this->addCallback(new ElementValueCallback($this->parseElementPath($path, $delimiter), $handler));
		}

		/**
		 * Collects the element values of all elements matching the given path
		 * @param string $path The path (relative to current element parser points to)
		 * @param mixed $out Will hold the array with collected values when parsing of all matching elements is complete
		 * @param string|callable|null $convert Converter for the element values. This could be either a callable or a string specifying one or multiple internal parsers, eg. "default:x|trim|upper"
		 * @param string $delimiter The path delimiter used
		 * @return XmlParser This instance
		 */
		public function collectValue(string $path, &$out, $convert = null, string $delimiter = '.') {
			$out = [];

			return $this->eachValue($path, function($v) use (&$out, $convert) {

				if ($convert !== null)
					$v = $this->convertValue($v, $convert);

				$out[] = $v;

			}, $delimiter);
		}

		/**
		 * Parses the value of first element matching the given path
		 * @param string $path The path (relative to current element parser points to)
		 * @param mixed $out Will hold the value when parsing of first matching element is complete
		 * @param string|callable|null $convert Converter for the element value. This could be either a callable or a string specifying one or multiple internal parsers, eg. "default:x|trim|upper"
		 * @param string $delimiter The path delimiter used
		 * @return XmlParser This instance
		 */
		public function value(string $path, &$out, $convert = null, string $delimiter = '.') {

			// if the value of the current node is required, this is a special case and we need to
			// use a different callback type which allows capturing the value without processing
			// the opening element
			if ($path == '') {

				if (!$this->elSelfClosing()) {
					return $this->addCallback(new CurrentElementValueCallback([], function ($v) use (&$out, $convert) {
						if ($convert !== null)
							$v = $this->convertValue($v, $convert);

						$out = $v;
					}));
				}
				else {
					// for self closing elements, we cannot add a callback but the output value is null anyways
					$v = null;

					if ($convert !== null)
						$v = $this->convertValue($v, $convert);

					$out = $v;

					return $this;
				}
			}

			$parsed = false;

			return $this->eachValue($path, function($v) use (&$out, &$parsed, $convert) {

				if (!$parsed) {

					if ($convert !== null)
						$v = $this->convertValue($v, $convert);

					$out = $v;
					$parsed = true;
				}

			}, $delimiter);
		}

		/**
		 * Collects the specified attribute values of elements matching the given path
		 * @param string $path The path (relative to current element parser points to)
		 * @param string $attribute The attribute to collect value of
		 * @param mixed $out Will hold the attribute value when parsing of all matching elements with specified attribute is complete
		 * @param string|callable|null $convert Converter for the attribute values. This could be either a callable or a string specifying one or multiple internal parsers, eg. "default:x|trim|upper"
		 * @param string $delimiter The path delimiter used
		 * @return XmlParser This instance
		 */
		public function collectAttribute(string $path, string $attribute, &$out, $convert = null, string $delimiter = '.') {
			$out = [];

			return $this->addCallback(new ElementStartCallback($this->parseElementPath($path, $delimiter), function (XmlParser $parser) use (&$out, &$parsed, $convert, $attribute) {

				$value = $parser->elAttribute($attribute);

				if ($value !== null) {

					if ($convert !== null)
						$value = $this->convertValue($value, $convert);
				}

				$out[] = $value;

			}));
		}

		/**
		 * Parses the specified attribute value of first element matching the given path
		 * @param string $path The path (relative to current element parser points to)
		 * @param string $attribute The attribute to return value of
		 * @param mixed $out Will hold the attribute value when first matching element with specified attribute is found
		 * @param string|callable|null $convert Converter for the attribute value. This could be either a callable or a string specifying one or multiple internal parsers, eg. "default:x|trim|upper"
		 * @param string $delimiter The path delimiter used
		 * @return XmlParser This instance
		 */
		public function attribute(string $path, string $attribute, &$out, $convert = null, string $delimiter = '.') {
			$parsed = false;

			return $this->addCallback(new ElementStartCallback($this->parseElementPath($path, $delimiter), function(XmlParser $parser) use (&$out, &$parsed, $convert, $attribute) {

				if (!$parsed) {

					$value = $parser->elAttribute($attribute);

					if ($value !== null) {

						if ($convert !== null)
							$value = $this->convertValue($value, $convert);

						$out    = $value;
						$parsed = true;
					}
				}
			}));
		}

		/**
		 * Unserializes the given target from first element matching given path
		 * @param string $path The path (relative to current element parser points to)
		 * @param XmlUnserialize|callable $target The target. Either a callable receiving the parser instance and returning the unserialized value or an instance of XmlUnserialize
		 * @param mixed $out Will hold the unserialized value when parsing of first matching element is complete
		 * @param string $delimiter The path delimiter used
		 * @return XmlParser This instance
		 */
		public function unserialize(string $path, $target, &$out = null, string $delimiter = '.') {
			$parsed = false;

			if (!(
				(is_object($target) && ($target instanceof XmlUnserialize)) ||
				is_callable($target)
			)) {
				throw new InvalidArgumentException('Target must be either a callable or an instance of ' . XmlUnserialize::class);
			}

			return $this->addCallback(new ElementStartCallback($this->parseElementPath($path, $delimiter), function(XmlParser $parser) use (&$out, &$parsed, $target) {

				if (!$parsed) {

					if (is_object($target) && !($target instanceof Closure)) {
						$target->xmlUnserialize($parser);
						$out = $target;
					}
					else {
						$out = call_user_func($target, $parser);
					}

					$parsed = true;
				}

			}));
		}

		/**
		 * Unserializes the given target from all elements matching given path
		 * @param string $path The path (relative to current element parser points to)
		 * @param XmlUnserialize|callable $target The target. Either a callable receiving the parser instance and returning the unserialized value or an instance of XmlUnserialize (in which case the object is cloned before calling xmlUnserialize() )
		 * @param mixed $out Will hold the unserialized values when parsing of all matching elements is complete
		 * @param string $delimiter The path delimiter used
		 * @return XmlParser This instance
		 */
		public function unserializeAll(string $path, $target, &$out = null, string $delimiter = '.') {
			$out = [];

			if (!(
				(is_object($target) && ($target instanceof XmlUnserialize)) ||
				is_callable($target)
			)) {
				throw new InvalidArgumentException('Target must be either a callable or an instance of ' . XmlUnserialize::class);
			}

			return $this->addCallback(new ElementStartCallback($this->parseElementPath($path, $delimiter), function(XmlParser $parser) use (&$out, $target) {

				if (is_object($target) && !($target instanceof Closure)) {
					$t = (clone $target);
					$t->xmlUnserialize($parser);
					$out[] = $t;
				}
				else {
					$out[] = call_user_func($target, $parser);
				}

			}));
		}


		/**
		 * Adds a new parser callback (relative to current node)
		 * @param XmlParserCallback $callback The callback
		 * @return $this
		 */
		public function addCallback(XmlParserCallback $callback) {

			$this->assertParsingNotContinued();

			$callbackPath = $callback->path();

			$emptyPath = !array_filter($callbackPath);

			if ($emptyPath) {
				if ($this->elType() !== XMLReader::ELEMENT || $this->elSelfClosing())
					throw new InvalidArgumentException('Path must not be empty if current element is not an opening element or is self closing!');
				if ($callback->types()[XMLReader::ELEMENT] ?? false)
					throw new InvalidArgumentException('Path must not be empty if callback handles opening elements!');
			}


			$depth = $this->depth;

			$path         = array_merge($this->pathSegments, $callbackPath);
			$pathMatchStr = $this->stringifyPath($path) . $this->pathDelimiter();

			$this->callbacks[$pathMatchStr][] = $callback;
			$callback->setAttachedLevel($depth);


			$this->invalidateActiveCallbacks($depth);
			$this->invalidateSubtreeCallbacks();

			return $this;
		}

		/**
		 * Invokes the registered callbacks for the current element to parser points to
		 */
		protected function handle() {

			$reader = $this->reader;

			$this->currentlyHandledElementId[] = $this->elementId;

			try {
				foreach ($this->activeCallbacks($this->depth) as $currCallback) {
					$nodeTypes = $currCallback->types();

					if ($nodeTypes === null || ($nodeTypes[$reader->nodeType] ?? false)) {
						// Check if parser moved on. If this is the case parsing cannot continue es intended
						// and we throw an exception
						$this->assertParsingNotContinued();

						$currCallback->handle($this);
					}
				}
			}
			finally {
				array_pop($this->currentlyHandledElementId);
			}

		}


		/**
		 * Reads the next element from reader
		 */
		protected function next() : bool {

			$reader = $this->reader;

			do {

				// pop path for closed elements
				if ($reader->nodeType === XMLReader::END_ELEMENT || ($reader->nodeType === XMLReader::ELEMENT && $reader->isEmptyElement))
					$this->popPath();



				// the end of the document is reached, when the root node has
				// been parsed and the depth is 0 again
				if ($this->rootParsed && $this->depth === 0)
					return false;


				// we only read the subtree of current node, if this required (this avoids
				// unnecessary parsing effort if node and content is not of interest)
				if (!$this->shouldParseElement()) {
					$this->e($reader->next());

					// next operation closes current sibling and moves on - since we opened the path for the
					// sibling already, we have to close it here right now
					$this->popPath();
				}
				else {
					$this->e($reader->read());
				}


				// append path for new elements
				if ($reader->nodeType === XMLReader::ELEMENT) {

					$this->pushPath($reader->localName, $reader->namespaceURI, $reader->prefix);



					// remember that we found the root node
					if (!$this->rootParsed)
						$this->rootParsed = true;
				}
			}
			while (!$this->shouldParseElement());


			++$this->elementId;

			return true;
		}

		/**
		 * Checks if the current element should be parsed or could be skipped
		 * @return bool True if to parse element. Else false.
		 */
		protected function shouldParseElement() : bool {

			return
				// only element nodes can be skipped => all others have to be parsed
				$this->reader->nodeType !== XMLReader::ELEMENT ||
				// check if any callbacks are active for current path
				$this->activeCallbacks($this->depth) ||
				// check if any callbacks exist for subtree
				$this->hasSubtreeCallbacks($this->pathMatchStr) ||
				// always parse at root level
				$this->depth <= 1;
		}


		/**
		 * Invalidates the active callbacks cache for given depth
		 * @param int $depth The depth
		 */
		protected function invalidateActiveCallbacks(int $depth) {

			// invalidate active callbacks for given depth
			unset($this->activeCallbacks[$depth]);
		}

		/**
		 * Returns the active callbacks for given depth
		 * @param int $depth The depth
		 * @return XmlParserCallback[] The active callbacks for given depth
		 */
		protected function activeCallbacks(int $depth) : array {

			$pathMatchStr = $depth === $this->depth ?
				$this->pathMatchStr :
				$this->makePathMatchString(array_slice($this->pathSegments, 0, $depth));


			return $this->activeCallbacks[$depth] ??
			       $this->activeCallbacks[$depth] = array_merge(
				       $this->callbacks[$pathMatchStr] ?? [],
				       ($depth === 1 ? $this->callbacks['>>'] ?? [] : []), // root node callbacks
				       $this->inheritableCallbacks($depth)
			       );
		}

		/**
		 * Returns all active and recursive callbacks to inherit from parent node
		 * @param int $depth The current node depth
		 * @return XmlParserCallback[] The inheritable callbacks
		 */
		protected function inheritableCallbacks($depth) {

			if ($depth < 1)
				return [];

			// get callbacks active for parent level
			$parentCallbacks = $depth === 1 ?
				$this->callbacks[$this->pathDelimiter()] ?? [] : // inherit root callbacks
				$this->activeCallbacks($depth - 1); // inherit active callbacks from parent


			// filter recursive callbacks
			$ret = [];
			foreach ($parentCallbacks as $currParentCallback) {
				if ($currParentCallback->recursive())
					$ret[] = $currParentCallback;
			}

			return $ret;
		}


		/**
		 * Invalidates the subtree callback cache
		 */
		protected function invalidateSubtreeCallbacks() {
			$this->subtreeCallbacks = [];
		}

		/**
		 * Checks if callbacks for the subtree of given path exist
		 * @param string $pathMatchStr The path match string
		 * @return bool True if existing. Else false.
		 */
		protected function hasSubtreeCallbacks(string $pathMatchStr) {

			$hasCallbacks = $this->subtreeCallbacks[$pathMatchStr] ?? null;

			if ($hasCallbacks === null) {

				$pathMatchStrLength = strlen($pathMatchStr);

				$hasCallbacks = false;
				$keys = array_keys($this->callbacks);
				foreach ($keys as $currKey) {
					if (substr($currKey, 0, $pathMatchStrLength) == $pathMatchStr) {
						$hasCallbacks = true;
						break;
					}
				}

				$this->subtreeCallbacks[$pathMatchStr] = $hasCallbacks;
			}


			return $hasCallbacks;

		}

		/**
		 * Builds a path match string
		 * @param array $pathSegments The path segments
		 * @return string The path match string
		 */
		protected function makePathMatchString(array $pathSegments) {
			return $this->stringifyPath($pathSegments) . $this->pathDelimiter();
		}

		/**
		 * Parses the given element path
		 * @param string $path The path
		 * @param string $delimiter The used path delimiter
		 * @return string[] The path segments
		 */
		protected function parseElementPath(string $path, string $delimiter = '.'): array {
			
			if ($path == '')
				return [];

			$delEscaped = preg_quote($delimiter, '/');

			if (!preg_match_all("/(\\{[^\\}]*\\})?[^{$delEscaped}]+/u", $path . '', $matches))
				throw new InvalidArgumentException("Invalid path given: \"{$path}\"");

			$segments = $matches[0];

			foreach ($segments as &$name) {

				if ($name[0] !== '{') {

					$prefix = null;
					if (($pos = strpos($name, ':')) !== false) {

						$prefix = substr($name, 0, $pos);
						$name   = substr($name, $pos + 1);
					}

					$uri = $this->resolvePrefixUri($prefix);

					if (($uri . '') !== '')
						$name = "{{$uri}}$name";

				}

			}

			return $segments;
		}

		/**
		 * Parses the given attribute name
		 * @param string $name The attribute name
		 * @return string[] The namespace URI or null and the argument name
		 */
		protected function parseAttributeName(string $name) : array {

			if ($name[0] === '{') {
				// uri
				
				$closingPos = strpos($name, '}');

				if ($closingPos > 1) {
					$uri  = substr($name, 1, $closingPos - 1);
					$name = substr($name, $closingPos + 1);

					return [$uri !== '' ? $uri : null, $name];
				}
			}

			if (($pos = strpos($name, ':')) !== false) {
				// prefix

				$prefix = substr($name, 0, $pos);
				$name   = substr($name, $pos + 1);

				return [$this->resolvePrefixUri($prefix, false), $name];
			}
			else {
				// not namespaced
				return [null, $name];
			}
		}


		/**
		 * Resolves the URI for given prefix
		 * @param string|null $prefix The prefix
		 * @param bool $useXmlns True if to return current xmlns when `null` is passed
		 * @return string|null The URI or `null` if NULL-namespace should be used
		 */
		protected function resolvePrefixUri(?string $prefix, bool $useXmlns = true) : ?string {

			// if prefix is null, we return the current XML default namespace
			if ($prefix === null) {
				if ($useXmlns) {
					$ret = end($this->xmlnsStack);

					return ($ret !== false ? $ret : '');
				}
				else {
					return null;
				}
			}

			// If prefix is empty string, we return the namespace of current element.
			// This allows eg. ":child.:otherChild" to match child nodes of same namespace
			// as current node
			if ($prefix === '')
				return $this->reader->namespaceURI;

			$uri = $this->prefixes[$prefix] ?? null;

			if ($uri === null)
				throw new InvalidArgumentException("No URI for XML prefix \"$prefix\" specified");

			return $uri;
		}

		/**
		 * Pushes a new path segment
		 * @param string $name The element name
		 * @param string|null $namespaceUri The element namespace URI
		 * @param string|null $prefix The namespace prefix the current node uses
		 */
		protected function pushPath(string $name, string $namespaceUri = null, string $prefix = null) {
			++$this->depth;
			$this->pathSegments[] = $this->prependNamespace($name, $namespaceUri);

			// update match string version of path
			$this->pathMatchStr = $this->stringifyPath($this->pathSegments) . $this->pathDelimiter();

			if ($namespaceUri && ($prefix . '') === '')
				$this->xmlnsStack[] = $namespaceUri;
			else
				$this->xmlnsStack[] = ($this->xmlnsStack[0] ?? null) !== null ? end($this->xmlnsStack) : '';

			$this->endCallbacks[] = [];
		}

		/**
		 * Pops the last path segment
		 */
		protected function popPath() {
			$this->invalidateActiveCallbacks($this->depth);

			$depth = --$this->depth;
			array_pop($this->pathSegments);

			// update match string version of path
			$this->pathMatchStr = $this->stringifyPath($this->pathSegments) . $this->pathDelimiter();

			array_pop($this->xmlnsStack);

			// remove callbacks attached to higher level
			foreach($this->callbacks as $k => &$callbacks) {
				foreach($callbacks as $index => $cb) {
					if ($cb->getAttachedLevel() > $depth)
						unset($callbacks[$index]);
				}

				if (!$callbacks)
					unset($this->callbacks[$k]);
			}

			// pop and invoke end callbacks
			$endCallbacks = array_pop($this->endCallbacks);
			foreach ($endCallbacks as $currCallback) {
				call_user_func($currCallback);
			}
		}


		/**
		 * Encodes the given string for output
		 * @param string|null $str The string to decode
		 * @return string|null The string
		 */
		protected function decodeString(?string $str) {
			if ($str === null)
				return null;

			if ('UTF-8' == $this->outputEncoding)
				return $str;
			else
				return mb_convert_encoding($str, $this->outputEncoding, 'UTF-8');
		}

		/**
		 * Prepends the namespace URI to node name
		 * @param string $name The node name
		 * @param string|null $namespaceUri The namespace URI
		 * @return string The prepended name
		 */
		protected function prependNamespace(string $name, string $namespaceUri = null) {
			if ($namespaceUri)
				$namespaceUri = "{{$namespaceUri}}";

			return "{$namespaceUri}{$name}";
		}

		protected function assertParsingNotContinued() {
			$curr = $this->currentlyHandledElementId;

			if (end($curr) !== $this->elementId)
				throw new RuntimeException('Parsing has already been continued by another handler. The designated element is not available anymore.');
		}
	}