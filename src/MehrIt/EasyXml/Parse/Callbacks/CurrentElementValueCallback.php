<?php


	namespace MehrIt\EasyXml\Parse\Callbacks;


	use MehrIt\EasyXml\Parse\XmlParser;
	use RuntimeException;
	use XMLReader;

	class CurrentElementValueCallback extends AbstractCallback
	{
		protected $path;
		protected $recursive = false;
		protected $handler;

		protected $level = 1;

		protected $val = null;

		/**
		 * ElementStartCallback constructor.
		 * @param $path
		 * @param $handler
		 */
		public function __construct(array $path, callable $handler) {
			$this->path    = $path;
			$this->handler = $handler;
		}

		/**
		 * @inheritDoc
		 */
		public function path(): array {
			return $this->path;
		}

		/**
		 * @inheritDoc
		 */
		public function recursive(): bool {
			return false;
		}

		/**
		 * @inheritDoc
		 */
		public function types(): ?array {
			return [
				XMLReader::SIGNIFICANT_WHITESPACE => true,
				XMLReader::TEXT                   => true,
				XMLReader::END_ELEMENT            => true,
				XMLReader::CDATA                  => true,
			];
		}

		/**
		 * @inheritDoc
		 */
		public function handle(XmlParser $parser) {

			if ($this->level < 1)
				throw new RuntimeException('The current element value callback can only be used once.');

			switch ($parser->elType()) {

				case XMLReader::TEXT:
				case XMLReader::SIGNIFICANT_WHITESPACE:
				case XMLReader::CDATA:
					$this->val .= $parser->elValue();
					break;

				case XMLReader::END_ELEMENT:
					--$this->level;

					if ($this->level <= 1)
						$this->onEnd();

			}
		}


		protected function onEnd() {
			call_user_func($this->handler, $this->val);

			// reset
			$this->level = 0;
			$this->val   = null;
		}


	}