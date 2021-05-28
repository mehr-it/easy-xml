<?php


	namespace MehrIt\EasyXml\Parse\Callbacks;


	use MehrIt\EasyXml\Contracts\XmlParserCallback;

	abstract class AbstractCallback implements XmlParserCallback
	{
		protected $attachedLevel = -1;

		public function setAttachedLevel(int $level): XmlParserCallback {
			$this->attachedLevel = $level;

			return $this;
		}

		public function getAttachedLevel(): int {
			return $this->attachedLevel;
		}
	}