<?php
	/**
	 * Created by PhpStorm.
	 * User: chris
	 * Date: 06.03.19
	 * Time: 16:36
	 */

	namespace MehrIt\EasyXml\Build\Serialize;


	use MehrIt\EasyXml\Build\XmlBuilder;
	use MehrIt\EasyXml\Contracts\XmlSerializable;

	class WhenSerializer implements XmlSerializable
	{

		protected $condition;
		protected $callback;

		/**
		 * Creates a new instance
		 * @param \Closure|mixed $condition The condition. Either a vale or a closure
		 * @param callable $callback The callback. Will receive the builder.
		 */
		public function __construct($condition, callable $callback) {
			$this->condition = $condition;
			$this->callback  = $callback;
		}

		/**
		 * @inheritDoc
		 */
		public function xmlSerialize(XmlBuilder $builder) {
			$condition = $this->condition;

			if ($condition instanceof \Closure)
				$condition = call_user_func($condition);

			if ($condition)
				call_user_func($this->callback, $builder);
		}


	}