<?php


	namespace MehrIt\EasyXml\Parse\Callbacks;


	use MehrIt\EasyXml\Parse\XmlParser;
	use XMLReader;

	class ElementStartCallback extends AbstractCallback
	{
		protected $path;
		protected $recursive = false;
		protected $handler;

		/**
		 * ElementStartCallback constructor.
		 * @param $path
		 * @param bool $recursive
		 * @param $handler
		 */
		public function __construct(array $path, callable $handler, bool $recursive = false) {
			$this->path      = $path;
			$this->recursive = $recursive;
			$this->handler   = $handler;
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
			return $this->recursive;
		}

		/**
		 * @inheritDoc
		 */
		public function types(): ?array {
			return [
				XMLReader::ELEMENT => true,
			];
		}

		/**
		 * @inheritDoc
		 */
		public function handle(XmlParser $parser) {

			call_user_func($this->handler, $parser);

		}


	}