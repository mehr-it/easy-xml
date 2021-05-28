<?php
	/**
	 * Created by PhpStorm.
	 * User: chris
	 * Date: 06.03.19
	 * Time: 18:03
	 */

	namespace MehrIt\EasyXml\Build\Serialize;



	use MehrIt\EasyXml\Build\XmlBuilder;
	use MehrIt\EasyXml\Contracts\XmlSerializable;

	class TernarySerializer implements XmlSerializable
	{
		protected $expression;
		protected $then;
		protected $else;

		/**
		 * Creates a new instance
		 * @param \Closure|mixed $expression The expression. Either a vale or a closure
		 * @param \Closure|mixed|null $then The value to write if expression is truthy. If \Closure is passed it wil receive the expression value and must return the value to write
		 * @param \Closure|mixed|null $else The value to write if expression is falsy. If \Closure is passed it wil receive the expression value and must return the value to write
		 */
		public function __construct($expression, $then, $else = null) {
			$this->expression = $expression;
			$this->then       = $then;
			$this->else       = $else;
		}

		/**
		 * @inheritDoc
		 */
		public function xmlSerialize(XmlBuilder $builder) {
			$expression = $this->expression;

			if ($expression instanceof \Closure)
				$expression = call_user_func($expression);

			$val = $expression ? $this->then : $this->else;

			if ($val instanceof \Closure)
				$val = call_user_func($val, $expression);

			$builder->write($val);
		}
	}