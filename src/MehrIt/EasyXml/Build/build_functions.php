<?php

	namespace MehrIt\EasyXml\Build;

	use MehrIt\EasyXml\Build\Serialize\EachSerializer;
	use MehrIt\EasyXml\Build\Serialize\MapSerializer;
	use MehrIt\EasyXml\Build\Serialize\TernarySerializer;
	use MehrIt\EasyXml\Build\Serialize\WhenSerializer;

	/**
	 * Calls the given callback for each item of the collection with the builder
	 * @param \Traversable|array $collection The collection
	 * @param callable $callback The callback. Will receive the builder, the value and the key
	 * @return EachSerializer
	 */
	function each($collection, callable $callback) {
		return new EachSerializer($collection, $callback);
	}

	/**
	 * Calls the given callback for each item of the collection and writes the result
	 * @param \Traversable|array $collection The collection
	 * @param callable $callback The callback. Will receive the value and the key and must return the value to write
	 * @return MapSerializer
	 */
	function map($collection, callable $callback) {
		return new MapSerializer($collection, $callback);
	}

	/**
	 * Calls the given callback when either truthy value or a closure returning a truthy value is passed
	 * @param \Closure|mixed $condition The condition. Either a vale or a closure
	 * @param callable $callback The callback. Will receive the builder.
	 * @return WhenSerializer
	 */
	function when($condition, callable $callback) {
		return new WhenSerializer($condition, $callback);
	}

	/**
	 * Writes one of the given values based on the given expression
	 * @param \Closure|mixed $expression The expression. Either a vale or a closure. If truthy, the "then" argument is returned. Else the "else" argument
	 * @param \Closure|mixed|null $then The value to write if expression is truthy. If \Closure is passed it wil receive the expression value and must return the value to write
	 * @param \Closure|mixed|null $else The value to write if expression is falsy. If \Closure is passed it wil receive the expression value and must return the value to write
	 * @return TernarySerializer
	 */
	function ternary($expression, $then, $else = null) {
		return new TernarySerializer($expression, $then, $else);
	}