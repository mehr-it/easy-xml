<?php
	/**
	 * Created by PhpStorm.
	 * User: chris
	 * Date: 06.03.19
	 * Time: 16:38
	 */

	namespace MehrIt\EasyXml\Build\Serialize;


	use MehrIt\EasyXml\Build\XmlBuilder;
	use MehrIt\EasyXml\Contracts\XmlSerializable;

	class MapSerializer implements XmlSerializable
	{
		protected $callback;
		protected $collection;

		/**
		 * Creates a new instance
		 * @param \Traversable|array $collection The collection
		 * @param callable $callback The callback. Will receive the the value and the key and must return the value to write
		 */
		public function __construct($collection, callable $callback) {
			$this->callback   = $callback;
			$this->collection = $collection;
		}


		/**
		 * @inheritDoc
		 */
		public function xmlSerialize(XmlBuilder $builder) {

			foreach ($this->collection as $key => $value) {
				$builder->write(call_user_func_array($this->callback, [$value, $key]));
			}

		}


	}