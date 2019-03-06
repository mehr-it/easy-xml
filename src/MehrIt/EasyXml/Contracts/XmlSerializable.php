<?php
	/**
	 * Created by PhpStorm.
	 * User: chris
	 * Date: 05.03.19
	 * Time: 10:08
	 */

	namespace MehrIt\EasyXml\Contracts;


	use MehrIt\EasyXml\Build\XmlBuilder;

	interface XmlSerializable
	{
		/**
		 * Serializes the object as XML
		 * @param XmlBuilder $builder The builder
		 */
		public function xmlSerialize(XmlBuilder $builder);
	}