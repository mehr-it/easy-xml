<?php
	/**
	 * Created by PhpStorm.
	 * User: chris
	 * Date: 05.03.19
	 * Time: 09:18
	 */

	namespace MehrIt\EasyXml\Build;


	use Closure;
	use InvalidArgumentException;
	use MehrIt\EasyXml\Build\Serialize\EachSerializer;
	use MehrIt\EasyXml\Build\Serialize\MapSerializer;
	use MehrIt\EasyXml\Build\Serialize\TernarySerializer;
	use MehrIt\EasyXml\Build\Serialize\WhenSerializer;
	use MehrIt\EasyXml\Contracts\XmlSerializable;
	use MehrIt\EasyXml\Exception\XmlException;
	use MehrIt\EasyXml\Stream\StreamWrapper;
	use MehrIt\EasyXml\XmlErrors;
	use RuntimeException;
	use XMLWriter;

	/** @noinspection PhpInconsistentReturnPointsInspection */

	/**
	 * Builder for creating XML (uses XMLWriter internally)
	 * @package MehrIt\EasyXml
	 */
	class XmlBuilder
	{
		use XmlErrors;

		/**
		 * @var XMLWriter
		 */
		protected $writer;

		protected $stack = [];

		protected $handlers = [];
		protected $attributeHandlers = [];

		protected $namespaces = [];
		protected $startedAttributeDefinesNamespace = false;
		protected $startedAttributeValue = '';
		protected $rootNodeExists = false;

		protected $inputEncoding;

		protected $usingMemory = false;

		protected $autoFlush = false;
		protected $bufferSize = 0;

		protected $streamWrapperId = null;

		/**
		 * Creates a new instance
		 * @param string|null|resource $target The URI to write to, a resource or empty. If empty, the builder writes to memory
		 */
		public function __construct($target = null) {

			$this->writer = new XMLWriter();

			// open URI or memory
			if (is_resource($target)) {
				// resource (XMLWriter only supports URLs, so stream wrapper is used to create an URL for accessing the resource)
				$id = StreamWrapper::register($target, null, true);
				$this->withXmlErrorHandler(function() use ($id) {
					return $this->writer->openUri('wrapper://' . $id);
				});
				$this->streamWrapperId = $id;
			}
			elseif (is_string($target)) {
				// URI
				$this->withXmlErrorHandler(function() use ($target) {
					return $this->writer->openUri($target);
				});
			}
			else {
				// memory
				$this->withXmlErrorHandler(function() {
					return $this->writer->openMemory();
				});
				$this->usingMemory = true;
			}


			$this->inputEncoding = mb_internal_encoding();
		}

		/**
		 * @inheritDoc
		 */
		public function __destruct() {

			// The XML writer might still try to flush the inner stream. If it was already closed, we can now ignore the corresponding error here
			if ($this->streamWrapperId)
				StreamWrapper::ignoreClosedFlush($this->streamWrapperId);
		}


		/**
		 * Activates automatic flushing
		 * @param int $bufferSize The internal buffer size. Default is 1MB. Note: the buffer size might exceed this limit because internal buffer size is not accurately calculated on every write for better performance. However, the buffer size is checked after every operation which possibly added a larger amount of data.
		 * @return $this
		 */
		public function autoFlush(int $bufferSize = 1048576) {
			if ($this->usingMemory)
				throw new RuntimeException('Automatic flush cannot be used when writing to memory');
			if ($bufferSize <= 0)
				throw new InvalidArgumentException('Buffer size must be greater than 0');

			$this->autoFlush = $bufferSize;

			return $this;
		}


		/**
		 * Creates the document tag
		 * @param string $version The XML version
		 * @param string|null $encoding The encoding. Default is UTF-8
		 * @param string|null $standalone The standalone directive
		 * @return $this
		 */
		public function startDocument(string $version = '1.0', string $encoding = null, string $standalone = null) {
			if ($this->stack !== [])
				throw new XmlException(null,'Cannot start a new document if document already created');
			$this->stack[] = 'doc';

			if (!$encoding)
				$encoding = 'UTF-8';

			$this->withXmlErrorHandler(function() use ($version, $encoding, $standalone) {
				return $this->writer->startDocument($version, $encoding, $standalone);
			});

			return $this;
		}


		/**
		 * Ends the document. If automatic flushing is active, this will flush any remaining data.
		 * @return $this
		 */
		public function endDocument() {
			if (end($this->stack) !== 'doc')
				throw new XmlException(null,'Cannot end document because no document was started or a document element is still open');

			$this->withXmlErrorHandler(function() { return $this->writer->endDocument(); });

			// perform auto flush
			$this->performAutoFlush(true);

			return $this;
		}

		/**
		 * Flushes the buffer
		 * @param bool $empty Whether to empty the buffer or not
		 * @return string|int If writing to memory, the generated XML buffer, Else this function will write the buffer and return the number of written bytes.
		 */
		public function flush(bool $empty = true) {
			return $this->writer->flush($empty);
		}

		/**
		 * Returns the current buffer
		 * @param bool $flush True if to flush the internal buffer
		 * @return string
		 */
		public function output(bool $flush = true) {

			return $this->writer->outputMemory($flush);
		}

		/**
		 * Sets the input encoding. By default the internal PHP encoding is used
		 * @param string $encoding The input encoding
		 * @return $this
		 */
		public function setInputEncoding(string $encoding) {
			$this->inputEncoding = $encoding;

			return $this;
		}

		/**
		 * Sets the indention mode
		 * @param bool $indent True to indent. Else false.
		 * @param string $indentString The indention string. Only applied if indent is true
		 * @return $this
		 */
		public function setIndent(bool $indent, string $indentString = "\t") {

			$this->withXmlErrorHandler(function() use ($indent) {
				return $this->writer->setIndent($indent);
			});
			if ($indent)
				$this->withXmlErrorHandler(function() use ($indentString) {
					return $this->writer->setIndentString($indentString);
				});

			return $this;
		}

		/**
		 * Sets the handler for the given data type when passed as element content
		 * @param string $type The type (as returned by gettype()) or class name
		 * @param Closure $handler The handler function (will receive writer instance as first argument)
		 * @return $this
		 */
		public function setHandler($type, Closure $handler) {
			$this->handlers[ltrim($type, '\\')] = $handler;

			return $this;
		}

		/**
		 * Sets the handler for the given data type when passed as attribute value
		 * @param string $type The type (as returned by gettype()) or class name
		 * @param Closure $handler The handler function (will receive writer instance as first argument)
		 * @return $this
		 */
		public function setAttributeHandler($type, Closure $handler) {
			$this->attributeHandlers[ltrim($type, '\\')] = $handler;

			return $this;
		}

		/**
		 * Creates the start element tag
		 * @param string $name The tag name
		 * @param array $attributes The attributes. Name as key.
		 * @param bool $deduplicateNamespaces True if to try to deduplicate namespaces
		 * @return $this
		 */
		public function startElement(string $name, array $attributes = [], bool $deduplicateNamespaces = true) {
			if (!in_array(end($this->stack), ['el', 'doc']))
				throw new XmlException(null,'Cannot start element outside document or another element');
			if (end($this->stack) === 'doc' && $this->rootNodeExists)
				throw new XmlException(null,'Cannot create another element after root node');
			$this->stack[] = 'el';
			$this->rootNodeExists = true;

			$name = $this->encodeString($name);

			$this->namespaces[] = $this->extractNamespaces($attributes, $deduplicateNamespaces);

			// parse prefix
			[$prefix, $name, $uri] = $this->parseNamespace($name);

			// start element
			if ($prefix) {
				$this->withXmlErrorHandler(function () use ($prefix, $name, $uri) {
					return $this->writer->startElementNs($prefix, $name, $uri);
				});
			}
			else {
				$this->withXmlErrorHandler(function () use ($name) {
					return $this->writer->startElement($name);
				});
			}

			// auto flush
			$this->bufferSize += strlen($prefix . $name . $uri);
			$this->performAutoFlush();

			// append attributes
			$this->writeAttributes($attributes);

			return $this;
		}

		/**
		 * Ends the started element
		 * @param bool $forceEndTag If set to true, an end tag is written, even if element is empty
		 * @return $this
		 */
		public function endElement(bool $forceEndTag = false) {
			if (end($this->stack) !== 'el')
				throw new XmlException(null,'No element started');
			array_pop($this->stack);

			array_pop($this->namespaces);

			if (!$forceEndTag)
				$this->withXmlErrorHandler(function() { return $this->writer->endElement(); });
			else
				$this->withXmlErrorHandler(function() { return $this->writer->fullEndElement(); });

			return $this;
		}

		/**
		 * Creates the given element
		 * @param string $name The tag name
		 * @param string|array|Closure|XmlSerializable|null $content The content
		 * @param array $attributes The attributes
		 * @return $this
		 */
		public function writeElement(string $name, $content = null, array $attributes = []) {
			$this->startElement($name, $attributes);
			$this->write($content);
			$this->endElement();

			return $this;
		}


		/**
		 * Creates an attribute with given name
		 * @param string $name The attribute name
		 * @return $this
		 */
		public function startAttribute(string $name) {
			if (end($this->stack) !== 'el')
				throw new XmlException(null,'Cannot start attribute outside element');
			$this->stack[] = 'attr';

			$name = $this->encodeString($name);

			// parse prefix
			[$prefix, $name, $uri] = $this->parseNamespace($name);

			// start element
			if ($prefix) {
				$this->withXmlErrorHandler(function() use ($prefix, $name, $uri) {
					return $this->writer->startAttributeNs($prefix, $name, $uri);
				});

				// mark beginning of new namespace attribute
				if ($prefix == 'xmlns')
					$this->startedAttributeDefinesNamespace = $name;
			}
			else {
				$this->withXmlErrorHandler(function() use ($name) {
					return $this->writer->startAttribute($name);
				});
			}

			// auto flush
			$this->bufferSize += strlen($prefix . $name . $uri);
			$this->performAutoFlush();

			return $this;
		}

		/**
		 * Ends the started attribute
		 * @return $this
		 */
		public function endAttribute() {
			if (end($this->stack) !== 'attr')
				throw new XmlException(null,'No attribute started');
			array_pop($this->stack);

			if ($this->startedAttributeDefinesNamespace) {
				$level = count($this->namespaces) - 1;

				// remember namespace URI if this attribute defines a new namespace
				$this->namespaces[$level][$this->startedAttributeDefinesNamespace] = $this->startedAttributeValue;

				$this->startedAttributeDefinesNamespace = false;
				$this->startedAttributeValue            = '';
			}

			$this->withXmlErrorHandler(function() { return $this->writer->endAttribute(); });

			return $this;
		}

		/**
		 * Writes the given attribute
		 * @param string $name The attribute name
		 * @param string|null|mixed $value The value
		 * @return XmlBuilder
		 */
		public function writeAttribute(string $name, $value) {

			// check for handler
			$handlerName = gettype($value);
			if ($handlerName == 'object')
				$handlerName = ltrim(get_class($value), '\\');


			// apply handler if exists
			if ($handler = ($this->attributeHandlers[$handlerName] ?? null))
				$value = $handler($value, $this);


			$this->startAttribute($name);
			$this->text($value);
			$this->endAttribute();

			return $this;
		}

		/**
		 * Writes the given attributes
		 * @param array $attributes The attributes. Name as key.
		 * @return $this
		 */
		public function writeAttributes(array $attributes) {
			foreach ($attributes as $name => $value) {
				$this->writeAttribute($name, $value);
			}

			return $this;
		}



		/**
		 * Starts a new comment
		 * @return $this
		 */
		public function startComment() {
			if (!in_array(end($this->stack), ['el', 'doc']))
				throw new XmlException(null,'Cannot start comment outside document or element');
			$this->stack[] = 'comment';

			$this->withXmlErrorHandler(function() { return $this->writer->startComment(); });

			return $this;
		}

		/**
		 * Ends a comment
		 * @return $this
		 */
		public function endComment() {
			if (end($this->stack) !== 'comment')
				throw new XmlException(null,'No comment started');
			array_pop($this->stack);

			$this->withXmlErrorHandler(function() { return $this->writer->endComment(); });

			return $this;
		}

		/**
		 * Writes the given comment
		 * @param string $comment The comment
		 * @return $this
		 */
		public function writeComment($comment) {

			$this->startComment();
			$this->text($comment);
			$this->endComment();

			return $this;
		}

		/**
		 * Starts a CDATA block
		 * @return $this
		 */
		public function startCData() {
			if (!in_array(end($this->stack), ['el']))
				throw new XmlException(null,'Cannot start CDATA outside element');
			$this->stack[] = 'cdata';

			$this->withXmlErrorHandler(function() { return $this->writer->startCdata(); });

			return $this;
		}

		/**
		 * Ends a CDATA block
		 * @return $this
		 */
		public function endCData() {
			if (end($this->stack) !== 'cdata')
				throw new XmlException(null,'No CDATA started');
			array_pop($this->stack);

			$this->withXmlErrorHandler(function() { return $this->writer->endCdata(); });

			return $this;
		}

		/**
		 * Writes the given CDATA content
		 * @param string $content The content
		 * @return $this
		 */
		public function writeCData(?string $content) {
			$this->startCData();
			$this->data($content);
			$this->endCData();

			return $this;
		}

		/**
		 * Writes the given data to a CDATA block
		 * @param string $data The data
		 * @return $this
		 */
		public function data(?string $data) {
			if (end($this->stack) !== 'cdata')
				throw new XmlException(null,'Cannot append data outside CDATA block');

			$data = str_replace(']]>', ']]]]><![CDATA[>', $data);

			// even cdata must be in correct charset, so encode it
			$data = $this->encodeString($data);

			$this->withXmlErrorHandler(function() use ($data) {
				return $this->writer->text($data);
			});

			// auto flush
			$this->bufferSize += strlen($data);
			$this->performAutoFlush();

			return $this;
		}

		/**
		 * Writes the given text
		 * @param string|null $content The text
		 * @return $this
		 */
		public function text(?string $content) {
			if (!in_array(end($this->stack), ['el', 'attr', 'comment']))
				throw new XmlException(null,'Cannot append text outside element or attribute');

			if (end($this->stack) == 'comment' && mb_strpos($content, '-->') !== false)
				throw new XmlException(null,'Comment must not contain sequence "-->"');

			$content = $this->encodeString($content);

			// remember namespace URI if within namespace attribute
			if ($this->startedAttributeDefinesNamespace)
				$this->startedAttributeValue .= $content;

			$this->withXmlErrorHandler(function() use ($content) {
				return $this->writer->text($content);
			});

			// auto flush
			$this->bufferSize += strlen($content);
			$this->performAutoFlush();

			return $this;
		}

		/**
		 * Writes the given DTD
		 * @param string $name The DTD name
		 * @param string $identifier The identifier
		 * @return $this
		 */
		public function writeSystemDtd(string $name, string $identifier) {

			// DTD must be written before root node
			if ($this->rootNodeExists)
				throw new XmlException(null,'Cannot create DTD after root node');

			// encode
			$name       = $this->encodeString($name);
			$identifier = $this->encodeString($identifier);

			$this->withXmlErrorHandler(function() use ($name, $identifier) {
				return $this->writer->writeDtd($name, null, $identifier);
			});

			return $this;
		}

		/**
		 * Writes the given DTD
		 * @param string $name The DTD name
		 * @param string $publicIdentifier The public identifier
		 * @param string $systemIdentifier The system identifier
		 * @return $this
		 */
		public function writePublicDtd(string $name, string $publicIdentifier, string $systemIdentifier) {

			// DTD must be written before root node
			if ($this->rootNodeExists)
				throw new XmlException(null,'Cannot create DTD after root node');

			// encode
			$name             = $this->encodeString($name);
			$publicIdentifier = $this->encodeString($publicIdentifier);
			$systemIdentifier = $this->encodeString($systemIdentifier);

			$this->withXmlErrorHandler(function() use ($name, $publicIdentifier, $systemIdentifier) {
				return $this->writer->writeDtd($name, $publicIdentifier, $systemIdentifier);
			});

			return $this;
		}


		/**
		 * Writes the given data
		 * @param string|array|Closure|XmlSerializable|null $data The data. This could be a simple string, an array defining an element, a closure or and XmlSerializable instance.
		 * @return $this
		 */
		public function write($data) {

			if (is_array($data)) {
				if ($data['>'] ?? null) {

					$tag        = null;
					$content    = null;
					$attributes = [];
					foreach ($data as $k => $v) {
						switch ($k) {
							case '>':
								// tag
								$tag = $v;
								break;

							case '@':
								// content
								$content = $v;
								break;

							default:

								switch($k[0] ?? null) {
									case '@':
										// attributes
										$attributes[substr($k, 1)] = $v;
										break;

									case ':':
										// namespace definitions
										$attributes['xmlns' . $k] = $v;
										break;

									default:
										throw new InvalidArgumentException("Expected attribute name prefixed with \"@\" or \":\", got \"$k\"");

								}


						}
					}

					$this->writeElement($tag, $content, $attributes);
				}
				else {
					foreach ($data as $key => $value) {
						if (is_int($key)) {
							$this->write($value);
						}
						elseif ($key[0] === '<') {
							if (!is_array($value))
								throw new InvalidArgumentException("Expected array as value of key \"$key\"");

							// set tag in value and write value array
							$value['>'] = substr($key, 1);
							$this->write($value);
						}
						else {
							$this->writeElement($key, $value);
						}
					}
				}
			}
			elseif ($data instanceof Closure) {
				$data($this);
			}
			elseif ($data instanceof XmlSerializable) {
				$data->xmlSerialize($this);
			}
			elseif ($data !== null) {

				// check for handler
				$handlerName = gettype($data);
				if ($handlerName == 'object')
					$handlerName = ltrim(get_class($data), '\\');


				// use handler or add as text if not handler exists
				if ($handler = ($this->handlers[$handlerName] ?? null))
					$handler($data, $this);
				else
					$this->text((string)$data);
			}


			return $this;
		}

		/**
		 * Calls the given callback for each item of the collection with this builder
		 * @param \Traversable|array $collection The collection
		 * @param callable $callback The callback. Will receive the builder, the value and the key
		 * @return $this
		 */
		public function each($collection, callable $callback) {
			$this->write(new EachSerializer($collection, $callback));

			return $this;
		}

		/**
		 * Calls the given callback for each item of the collection and writes the result
		 * @param \Traversable|array $collection The collection
		 * @param callable $callback The callback. Will receive the value and the key and must return the value to write
		 * @return $this
		 */
		public function map($collection, callable $callback) {
			$this->write(new MapSerializer($collection, $callback));

			return $this;
		}

		/**
		 * Calls the given callback when either truthy value or a closure returning a truthy value is passed
		 * @param Closure|mixed $condition The condition. Either a vale or a closure
		 * @param callable $callback The callback. Will receive the builder.
		 * @return $this
		 */
		public function when($condition, callable $callback) {
			$this->write(new WhenSerializer($condition, $callback));

			return $this;
		}

		/**
		 * Writes one of the given values based on the given expression
		 * @param Closure|mixed $expression The expression. Either a vale or a closure. If truthy, the "then" argument is returned. Else the "else" argument
		 * @param Closure|mixed|null $then The value to write if expression is truthy. If \Closure is passed it wil receive the expression value and must return the value to write
		 * @param Closure|mixed|null $else The value to write if expression is falsy. If \Closure is passed it wil receive the expression value and must return the value to write
		 * @return $this
		 */
		public function ternary($expression, $then, $else = null) {
			$this->write(new TernarySerializer($expression, $then, $else));

			return $this;
		}

		/**
		 * Throws an XML exception if false is passed
		 * @param mixed $value The value
		 * @return $this
		 */
		protected function _e($value) {
			if ($value === false)
				throw new XmlException(null,'XMLWriter error');

			return $this;
		}

		/**
		 * Returns whether the given name is reserved
		 * @param string $name The name
		 * @return bool True if reserved. Else false.
		 */
		protected function isReservedName(string $name) {
			return substr($name, 0, 3) == 'xml';
		}

		/**
		 * Encodes the given string
		 * @param string|null $str The string to encode
		 * @return string|null The string
		 */
		protected function encodeString(?string $str) {
			if ($str === null)
				return null;

			if ('UTF-8' == $this->inputEncoding)
				return $str;
			else
				return mb_convert_encoding($str, 'UTF-8', $this->inputEncoding);
		}

		/**
		 * Parses prefix, name and URI from given name
		 * @param string $name The name
		 * @return string[] Array with three items: prefix, name and URI (if namespace must be declared)
		 */
		protected function parseNamespace(string $name) {

			// check for URI notation
			if (mb_substr($name, 0, 1, 'UTF-8') == '{') {

				$closingPos = mb_strpos($name, '}', 0, 'UTF-8');

				if ($closingPos > 1) {
					$isNewPrefix = false;
					$uri    = mb_substr($name, 1, $closingPos - 1, 'UTF-8');
					$name   = mb_substr($name, $closingPos + 1);
					$prefix = $this->getPrefixForNamespace($uri, $isNewPrefix);

					return [$prefix, $name, ($isNewPrefix ? $uri : null)];
				}
			}

			// check for prefix notation
			if ($pos = mb_strpos($name, ':', 0, 'UTF-8')) {

				$prefix = mb_substr($name, 0, $pos);
				$name   = mb_substr($name, $pos + 1);

				// check if prefix exists (this check is ignored for reserved prefixes because they do not have to be defined)
				if (!$this->isReservedName($prefix) && !$this->isPrefixedDefined($prefix))
					throw new XmlException(null,"Prefix \"$prefix\" does not reference any namespace");

				return [$prefix, $name, null];
			}

			return [null, $name, null];
		}


		/**
		 * Gets the prefix for the given URI. If not existing a new prefix is generated
		 * @param string $uri The URI
		 * @param bool $isNew Returns whether a new prefix was generated or not
		 * @return string The new prefix
		 */
		protected function getPrefixForNamespace($uri, &$isNew = false) {
			$level = count($this->namespaces) - 1;
			$existingPrefixes = [];


			// try to find an existing prefix for the URI
			for ($i = $level; $i >= 0; --$i) {
				foreach($this->namespaces[$i] as $currPfx => $currUri) {
					if ($currUri == $uri) {
						$isNew = false;
						return $currPfx;
					}

					$existingPrefixes[$currPfx] = true;
				}
			}

			$isNew = true;

			// let's generate a new prefix for this uri
			$pfxChars = ['a','b','c','d','e','f','g','h','i','j','k','l','m','n','o','p','q','r','s','t','u','v','w','y','z']; // ("x" is missing to avoid reserved names)
			$pfxCharLen = count($pfxChars);
			$pfx = '';
			$outer = $pfxCharLen;
			// loop until we found a new unique prefix
			while (true) {

				// try to append each prefix char one after another
				for ($i = 0; $i < $pfxCharLen; ++$i) {
					$retPfx = $pfx . $pfxChars[$i];

					// if we found a new unique prefix, we register the namespace with it and return
					if (!($existingPrefixes[$retPfx] ?? null)) {
						$this->namespaces[$level][$retPfx] = $uri;

						return $retPfx;
					}
				}

				// We could not create a new prefix. We strip the last char from prefix and try another instead. If all chars are tried we do not strip and try a longer prefix
				if ($outer < $pfxCharLen - 1)
					$pfx = substr($pfx, 0, -1);
				else
					$outer = 0;

				$pfx = $pfx . $pfxChars[$outer];
				++$outer;
			}
		}

		/**
		 * Gets the prefix for the given namespace URI
		 * @param string $uri
		 * @return bool|string The prefix or `false`
		 */
		protected function getPrefix(string $uri) {
			$level = count($this->namespaces) - 1;

			// try to find an existing prefix for the URI
			for ($i = $level; $i >= 0; --$i) {
				foreach ($this->namespaces[$i] as $currPfx => $currUri) {
					if ($currUri == $uri)
						return $currPfx;

				}
			}

			return false;
		}

		/**
		 * Checks if the given prefix is defined
		 * @param string $prefix The prefix
		 * @return string|bool URI if defined. Else false.
		 */
		protected function isPrefixedDefined($prefix) {
			foreach($this->namespaces as $namespaces) {
				foreach($namespaces as $currPfx => $currUri) {
					if ($prefix == $currPfx)
						return true;
				}
			}

			return false;
		}

		/**
		 * Gets the namespaces defined in attributes
		 * @param array $attributes The attributes. Name as key.
		 * @param bool $removeExisting True if to remove attributes for existing namespaces
		 * @return string[] The namespaces. Prefix as key. URI as value.
		 */
		protected function extractNamespaces(array &$attributes, bool $removeExisting = true) {
			$namespaces = [];
			foreach($attributes as $key => $value) {
				if ($key === 'xmlns')
					$pfx = '';
				elseif (substr($key, 0, 6) == 'xmlns:')
					$pfx = substr($key, 6);
				else
					continue;


				if (!$removeExisting || $this->getPrefix($value) !== $pfx) {
					// prefix defines a new namespace
					$namespaces[$pfx] = $value;
				}
				else {
					// prefix already defined
					unset($attributes[$key]);
				}

			}

			return $namespaces;
		}

		/**
		 * Performs an automatic flush if buffer is full
		 * @param bool $force If true, the buffer is flushed even limit is not reached
		 */
		protected function performAutoFlush($force = false) {
			if ($this->autoFlush && ($force || $this->bufferSize > $this->autoFlush)) {
				$this->flush();
				$this->bufferSize = 0;
			}
		}



	}