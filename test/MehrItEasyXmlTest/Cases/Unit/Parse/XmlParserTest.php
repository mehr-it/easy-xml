<?php


	namespace MehrItEasyXmlTest\Cases\Unit\Parse;



	use MehrIt\EasyXml\Contracts\XmlUnserialize;
	use MehrIt\EasyXml\Parse\Callbacks\AbstractCallback;
	use MehrIt\EasyXml\Parse\Callbacks\ElementStartCallback;
	use MehrIt\EasyXml\Parse\NodeTypeNames;
	use MehrIt\EasyXml\Parse\XmlParser;
	use PHPUnit\Framework\TestCase;
	use RuntimeException;
	use XMLReader;

	class XmlParserTest extends TestCase
	{
		use NodeTypeNames;


		public function testFromString() {
			$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
					<root>a</root>";

			$parser = XmlParser::fromString($xml);
			$this->assertInstanceOf(XmlParser::class, $parser);

			$parser->value('root', $rootValue);
			$parser->parse();
			$this->assertSame('a', $rootValue);
		}

		public function testFromString_temporaryUrl() {
			$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
					<root>a</root>";

			$tmp = tempnam(sys_get_temp_dir(), 'MehrItXmlParserTest');

			try {
				$parser = XmlParser::fromString($xml, $tmp);
				$this->assertInstanceOf(XmlParser::class, $parser);

				$parser->value('root', $rootValue);
				$parser->parse();
				$this->assertSame('a', $rootValue);


				$this->assertSame($xml, file_get_contents($tmp));
			}
			finally {
				unlink($tmp);
			}
		}

		public function testConstruct_xmlReader() {

			$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
					<root>a</root>";

			$tmp = tempnam(sys_get_temp_dir(), 'MehrItXmlParserTest');

			try {
				file_put_contents($tmp, $xml);

				$reader = new XMLReader();
				$reader->open($tmp);

				$parser = new XmlParser($reader);
				$this->assertInstanceOf(XmlParser::class, $parser);

				$parser->value('root', $rootValue);
				$parser->parse();
				$this->assertSame('a', $rootValue);

			}
			finally {
				unlink($tmp);
			}
		}

		public function testConstruct_uri() {

			$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
					<root>a</root>";

			$tmp = tempnam(sys_get_temp_dir(), 'MehrItXmlParserTest');

			try {
				file_put_contents($tmp, $xml);

				$parser = new XmlParser($tmp);
				$this->assertInstanceOf(XmlParser::class, $parser);

				$parser->value('root', $rootValue);
				$parser->parse();
				$this->assertSame('a', $rootValue);

			}
			finally {
				unlink($tmp);
			}
		}

		public function testConstruct_resource() {
			$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
					<root>a</root>";

			$fh = fopen('php://memory', 'w+');
			fwrite($fh, $xml);
			rewind($fh);

			$parser = new XmlParser($fh);
			$this->assertInstanceOf(XmlParser::class, $parser);

			$parser->value('root', $rootValue);
			$parser->parse();
			$this->assertSame('a', $rootValue);

		}

		public function testConstruct_setsOutputEncodingToCurrent() {

			$currentEncoding = mb_internal_encoding();
			if ($currentEncoding !== 'UTF-8')
				$this->markTestSkipped('Test must be run with UTF-8 encoding');

			$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
					<root>ä</root>";

			$fh = fopen('php://memory', 'w+');
			fwrite($fh, $xml);
			rewind($fh);

			try {
				mb_internal_encoding('ISO-8859-1');
				$parser = new XmlParser($fh);
				$this->assertInstanceOf(XmlParser::class, $parser);
				$this->assertSame('ISO-8859-1', $parser->getOutputEncoding());

				$parser->value('root', $rootValue);
				$parser->parse();

			}
			finally {
				mb_internal_encoding($currentEncoding);
			}

			$this->assertSame('ä', utf8_encode($rootValue));


		}

		public function testConstruct_setOutputEncoding() {

			$currentEncoding = mb_internal_encoding();
			if ($currentEncoding !== 'UTF-8')
				$this->markTestSkipped('Test must be run with UTF-8 encoding');

			$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
					<root>ä</root>";

			try {
				mb_internal_encoding('ISO-8859-1');
				$parser = XmlParser::fromString($xml);
				$this->assertSame('ISO-8859-1', $parser->getOutputEncoding());

				$this->assertSame($parser, $parser->setOutputEncoding('UTF-8'));
				$this->assertSame('UTF-8', $parser->getOutputEncoding());

				$parser->value('root', $rootValue);
				$parser->parse();

			}
			finally {
				mb_internal_encoding($currentEncoding);
			}

			$this->assertSame('ä', $rootValue);


		}

		public function testPrefix() {
			$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
					<root xmlns='http://mehr-it.info/EasyXmlParserTest'>a</root>";

			$parser = XmlParser::fromString($xml);

			$this->assertSame($parser, $parser->prefix('mit', 'http://mehr-it.info/EasyXmlParserTest'));

			$parser->value('mit:root', $rootValue);
			$parser->parse();
			$this->assertSame('a', $rootValue);
		}

		public function testPrefix_multiple() {
			$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
					<root xmlns=\"http://mehr-it.info/EasyXmlParserTest\" xmlns:m2=\"http://mehr-it.info/EasyXmlParserTest2\"><m2:b>a</m2:b></root>";

			$parser = XmlParser::fromString($xml);

			$this->assertSame($parser, $parser->prefix('mit', 'http://mehr-it.info/EasyXmlParserTest'));
			$this->assertSame($parser, $parser->prefix('mit2', 'http://mehr-it.info/EasyXmlParserTest2'));

			$parser->value('mit:root.mit2:b', $rootValue);
			$parser->parse();
			$this->assertSame('a', $rootValue);
		}

		public function testPrefix_alreadySet() {
			$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
					<root></root>";

			$parser = XmlParser::fromString($xml);

			$this->assertSame($parser, $parser->prefix('mit', 'http://mehr-it.info/EasyXmlParserTest'));

			$this->expectException(\InvalidArgumentException::class);

			$parser->prefix('mit', 'http://mehr-it.info/EasyXmlParserTest2');

		}

		public function testPrefix_alreadySet_butSameUri() {
			$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
					<root xmlns='http://mehr-it.info/EasyXmlParserTest'>a</root>";

			$parser = XmlParser::fromString($xml);

			$this->assertSame($parser, $parser->prefix('mit', 'http://mehr-it.info/EasyXmlParserTest'));
			$this->assertSame($parser, $parser->prefix('mit', 'http://mehr-it.info/EasyXmlParserTest'));

			$parser->value('mit:root', $rootValue);
			$parser->parse();
			$this->assertSame('a', $rootValue);
		}

		public function testElName() {
			$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
					<root>
						<el>a</el>
					</root>";

			$parser = XmlParser::fromString($xml);

			$parser->addCallback(new ElementStartCallback(['root', 'el'], function (XmlParser $parser) use (&$v) {
				$v = $parser->elName();
			}));
			$parser->parse();

			$this->assertSame('el', $v);
		}

		public function testElName_prefixedElement() {
			$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
					<root xmlns:a=\"http://mehr-it.info/EasyXmlParserTest\">
						<a:el>a</a:el>
					</root>";

			$parser = XmlParser::fromString($xml);

			$parser->addCallback(new ElementStartCallback(['root', '{http://mehr-it.info/EasyXmlParserTest}el'], function (XmlParser $parser) use (&$v) {
				$v = $parser->elName();
			}));
			$parser->parse();

			$this->assertSame('el', $v);
		}

		public function testElName_namespacedElement() {
			$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
					<root>
						<el xmlns=\"http://mehr-it.info/EasyXmlParserTest\">a</el>
					</root>";

			$parser = XmlParser::fromString($xml);

			$parser->addCallback(new ElementStartCallback(['root', '{http://mehr-it.info/EasyXmlParserTest}el'], function (XmlParser $parser) use (&$v) {
				$v = $parser->elName();
			}));
			$parser->parse();

			$this->assertSame('el', $v);
		}

		public function testElNamespaceUri() {
			$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
					<root>
						<el>a</el>
					</root>";

			$parser = XmlParser::fromString($xml);

			$parser->addCallback(new ElementStartCallback(['root', 'el'], function (XmlParser $parser) use (&$v) {
				$v = $parser->elNamespaceUri();
			}));
			$parser->parse();

			$this->assertSame(null, $v);
		}

		public function testElNamespaceUri_prefixedElement() {
			$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
					<root xmlns:a=\"http://mehr-it.info/EasyXmlParserTest\">
						<a:el>a</a:el>
					</root>";

			$parser = XmlParser::fromString($xml);

			$parser->addCallback(new ElementStartCallback(['root', '{http://mehr-it.info/EasyXmlParserTest}el'], function (XmlParser $parser) use (&$v) {
				$v = $parser->elNamespaceUri();
			}));
			$parser->parse();

			$this->assertSame('http://mehr-it.info/EasyXmlParserTest', $v);
		}

		public function testElNamespaceUri_namespacedElement() {
			$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
					<root>
						<el xmlns=\"http://mehr-it.info/EasyXmlParserTest\">a</el>
					</root>";

			$parser = XmlParser::fromString($xml);

			$parser->addCallback(new ElementStartCallback(['root', '{http://mehr-it.info/EasyXmlParserTest}el'], function (XmlParser $parser) use (&$v) {
				$v = $parser->elNamespaceUri();
			}));
			$parser->parse();

			$this->assertSame('http://mehr-it.info/EasyXmlParserTest', $v);
		}


		public function testElHasAttributes() {
			$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
					<root>
						<el z=\"0\">a</el>
					</root>";

			$parser = XmlParser::fromString($xml);

			$parser->addCallback(new ElementStartCallback(['root', 'el'], function (XmlParser $parser) use (&$v) {
				$v = $parser->elHasAttributes();
			}));
			$parser->parse();

			$this->assertSame(true, $v);
		}

		public function testElHasAttributes_noAttr() {
			$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
					<root>
						<el>a</el>
					</root>";

			$parser = XmlParser::fromString($xml);

			$parser->addCallback(new ElementStartCallback(['root', 'el'], function (XmlParser $parser) use (&$v) {
				$v = $parser->elHasAttributes();
			}));
			$parser->parse();

			$this->assertSame(false, $v);
		}

		public function testElHasAttribute() {
			$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
					<root>
						<el z=\"0\">a</el>
					</root>";

			$parser = XmlParser::fromString($xml);

			$parser->addCallback(new ElementStartCallback(['root', 'el'], function (XmlParser $parser) use (&$v) {
				$v = $parser->elHasAttribute('z');
			}));
			$parser->parse();

			$this->assertSame(true, $v);
		}

		public function testElHasAttribute_namespaced() {
			$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
					<root xmlns:a=\"http://mehr-it.info/EasyXmlParserTest\">
						<el a:z=\"0\">a</el>
					</root>";

			$parser = XmlParser::fromString($xml);

			$parser->addCallback(new ElementStartCallback(['root', 'el'], function (XmlParser $parser) use (&$v) {
				$v = $parser->elHasAttribute('{http://mehr-it.info/EasyXmlParserTest}z');
			}));
			$parser->parse();

			$this->assertSame(true, $v);
		}

		public function testElHasAttribute_notAttr() {
			$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
					<root>
						<el>a</el>
					</root>";

			$parser = XmlParser::fromString($xml);

			$parser->addCallback(new ElementStartCallback(['root', 'el'], function (XmlParser $parser) use (&$v) {
				$v = $parser->elHasAttribute('z');
			}));
			$parser->parse();

			$this->assertSame(false, $v);
		}

		public function testElHasAttribute_onlyOther() {
			$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
					<root>
						<el y=\"0\">a</el>
					</root>";

			$parser = XmlParser::fromString($xml);

			$parser->addCallback(new ElementStartCallback(['root', 'el'], function (XmlParser $parser) use (&$v) {
				$v = $parser->elHasAttribute('z');
			}));
			$parser->parse();

			$this->assertSame(false, $v);
		}

		public function testElHasAttribute_onlyOtherNamespace() {
			$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
					<root xmlns:a=\"http://mehr-it.info/EasyXmlParserTest\">
						<el a:z=\"0\">a</el>
					</root>";

			$parser = XmlParser::fromString($xml);

			$parser->addCallback(new ElementStartCallback(['root', 'el'], function (XmlParser $parser) use (&$v) {
				$v = $parser->elHasAttribute('z');
			}));
			$parser->parse();

			$this->assertSame(false, $v);
		}

		public function testElAttribute() {
			$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
					<root>
						<el z=\"b\" y=\"c\">a</el>
					</root>";

			$parser = XmlParser::fromString($xml);

			$parser->addCallback(new ElementStartCallback(['root', 'el'], function (XmlParser $parser) use (&$v) {
				$v = $parser->elAttribute('z');
			}));
			$parser->parse();

			$this->assertSame('b', $v);
		}

		public function testElAttribute_namespaced() {
			$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
					<root xmlns:a=\"http://mehr-it.info/EasyXmlParserTest\">
						<el a:z=\"b\">a</el>
					</root>";

			$parser = XmlParser::fromString($xml);

			$parser->addCallback(new ElementStartCallback(['root', 'el'], function (XmlParser $parser) use (&$v) {
				$v = $parser->elAttribute('{http://mehr-it.info/EasyXmlParserTest}z');
			}));
			$parser->parse();

			$this->assertSame('b', $v);
		}

		public function testElAttribute_notAttr() {
			$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
					<root>
						<el>a</el>
					</root>";

			$parser = XmlParser::fromString($xml);

			$parser->addCallback(new ElementStartCallback(['root', 'el'], function (XmlParser $parser) use (&$v) {
				$v = $parser->elAttribute('z');
			}));
			$parser->parse();

			$this->assertSame(null, $v);
		}

		public function testElAttribute_onlyOther() {
			$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
					<root>
						<el y=\"0\">a</el>
					</root>";

			$parser = XmlParser::fromString($xml);

			$parser->addCallback(new ElementStartCallback(['root', 'el'], function (XmlParser $parser) use (&$v) {
				$v = $parser->elAttribute('z');
			}));
			$parser->parse();

			$this->assertSame(null, $v);
		}

		public function testElAttribute_onlyOtherNamespace() {
			$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
					<root xmlns:a=\"http://mehr-it.info/EasyXmlParserTest\">
						<el a:z=\"0\">a</el>
					</root>";

			$parser = XmlParser::fromString($xml);

			$parser->addCallback(new ElementStartCallback(['root', 'el'], function (XmlParser $parser) use (&$v) {
				$v = $parser->elAttribute('z');
			}));
			$parser->parse();

			$this->assertSame(null, $v);
		}

		public function testElAttributeEach() {
			$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
					<root xmlns:a=\"http://mehr-it.info/EasyXmlParserTest\">
						<el a:x=\"b\" y=\"c\" z=\"\">a</el>
					</root>";

			$parser = XmlParser::fromString($xml);

			$i = 0;

			$parser->addCallback(new ElementStartCallback(['root', 'el'], function (XmlParser $parser) use (&$i) {

				$this->assertSame($parser, $parser->elAttributesEach(function ($name, $value, $ns) use (&$i) {
					switch ($i++) {
						case 0:
							$this->assertSame('x', $name);
							$this->assertSame('b', $value);
							$this->assertSame('http://mehr-it.info/EasyXmlParserTest', $ns);
							break;

						case 1:
							$this->assertSame('y', $name);
							$this->assertSame('c', $value);
							$this->assertSame(null, $ns);
							break;

						case 2:
							$this->assertSame('z', $name);
							$this->assertSame('', $value);
							$this->assertSame(null, $ns);
							break;
					}
				}));

				// check that parser points back to element
				$this->assertSame('el', $parser->elName());

			}));
			$parser->parse();

			$this->assertSame(3, $i);
		}

		public function testElAttributeEach_noAttr() {
			$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
					<root>
						<el>a</el>
					</root>";

			$parser = XmlParser::fromString($xml);

			$i = 0;

			$parser->addCallback(new ElementStartCallback(['root', 'el'], function (XmlParser $parser) use (&$i) {

				$parser->elAttributesEach(function ($name, $value, $ns) use (&$i) {
					++$i;
				});

				// check that parser points back to element
				$this->assertSame('el', $parser->elName());

			}));
			$parser->parse();

			$this->assertSame(0, $i);
		}

		public function testElSelfClosing() {
			$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
					<root>
						<el/>
					</root>";

			$parser = XmlParser::fromString($xml);

			$parser->addCallback(new ElementStartCallback(['root', 'el'], function (XmlParser $parser) use (&$v) {
				$v = $parser->elSelfClosing();
			}));
			$parser->parse();

			$this->assertSame(true, $v);
		}

		public function testElSelfClosing_emptyContent() {
			$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
					<root>
						<el></el>
					</root>";

			$parser = XmlParser::fromString($xml);

			$parser->addCallback(new ElementStartCallback(['root', 'el'], function (XmlParser $parser) use (&$v) {
				$v = $parser->elSelfClosing();
			}));
			$parser->parse();

			$this->assertSame(false, $v);
		}

		public function testElSelfClosing_withContent() {
			$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
					<root>
						<el>a</el>
					</root>";

			$parser = XmlParser::fromString($xml);

			$parser->addCallback(new ElementStartCallback(['root', 'el'], function (XmlParser $parser) use (&$v) {
				$v = $parser->elSelfClosing();
			}));
			$parser->parse();

			$this->assertSame(false, $v);
		}

		public function testElClosing_selfClosing() {
			$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
					<root>
						<el/>
					</root>";

			$parser = XmlParser::fromString($xml);

			$parser->addCallback(new ElementStartCallback(['root', 'el'], function (XmlParser $parser) use (&$v) {
				$v = $parser->elClosing();
			}));
			$parser->parse();

			$this->assertSame(true, $v);
		}

		public function testElClosing_start_emptyContent() {
			$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
					<root>
						<el></el>
					</root>";

			$parser = XmlParser::fromString($xml);

			$parser->addCallback(new ElementStartCallback(['root', 'el'], function (XmlParser $parser) use (&$v) {
				$v = $parser->elClosing();
			}));
			$parser->parse();

			$this->assertSame(false, $v);
		}

		public function testElClosing_end_emptyContent() {
			$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
					<root>
						<el></el>
					</root>";

			$parser = XmlParser::fromString($xml);

			$parser->addCallback(new XmlParserTestElementEndCallback(['root', 'el'], function (XmlParser $parser) use (&$v) {
				$v = $parser->elClosing();
			}));
			$parser->parse();

			$this->assertSame(true, $v);
		}

		public function testElClosing_start_withContent() {
			$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
					<root>
						<el>a</el>
					</root>";

			$parser = XmlParser::fromString($xml);

			$parser->addCallback(new ElementStartCallback(['root', 'el'], function (XmlParser $parser) use (&$v) {
				$v = $parser->elClosing();
			}));
			$parser->parse();

			$this->assertSame(false, $v);
		}

		public function testElClosing_end_withContent() {
			$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
					<root>
						<el>a</el>
					</root>";

			$parser = XmlParser::fromString($xml);

			$parser->addCallback(new XmlParserTestElementEndCallback(['root', 'el'], function (XmlParser $parser) use (&$v) {
				$v = $parser->elClosing();
			}));
			$parser->parse();

			$this->assertSame(true, $v);
		}

		public function testElType() {
			$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
					<root>
						<el>a</el>
					</root>";

			$parser = XmlParser::fromString($xml);

			$i = 0;

			$parser->addCallback(new XmlParserTestElementStartTextEndCallback(['root', 'el'], function (XmlParser $parser) use (&$i) {

				switch ($i++) {
					case 0:
						$this->assertSame(XMLReader::ELEMENT, $parser->elType());
						break;
					case 1:
						$this->assertSame(XMLReader::TEXT, $parser->elType());
						break;
					case 2:
						$this->assertSame(XMLReader::END_ELEMENT, $parser->elType());
						break;
				}
			}));
			$parser->parse();

			$this->assertSame(3, $i);
		}

		public function testElValue() {
			$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
					<root>
						<el>a<![CDATA[bc]]></el>
					</root>";

			$parser = XmlParser::fromString($xml);

			$i = 0;

			$parser->addCallback(new XmlParserTestElementStartTextEndCallback(['root', 'el'], function (XmlParser $parser) use (&$i) {

				switch ($i++) {

					case 1:
						$this->assertSame('a', $parser->elValue());
						break;

					case 2:
						$this->assertSame('bc', $parser->elValue());
						break;
				}
			}));
			$parser->parse();

			$this->assertSame(4, $i);
		}

		public function testElEnd() {
			$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
					<root>
						<el><b><c>5</c></b></el>
					</root>";

			$parser = XmlParser::fromString($xml);

			$stack = [];

			$parser->addCallback(new ElementStartCallback(['root', 'el', 'b'], function (XmlParser $parser) use (&$stack) {

				$parser->value('c', $v);

				$parser->elEnd(function() use (&$v, &$stack) {
					$stack[] = $v;
				});

				$stack[] = 'b-called';

			}));
			$parser->addCallback(new ElementStartCallback(['root', 'el'], function (XmlParser $parser) use (&$stack) {


				$parser->elEnd(function() use (&$v, &$stack) {
					$stack[] = 'el-called';
				});

			}));
			$parser->parse();

			$this->assertSame(['b-called', '5', 'el-called'], $stack);
		}

		public function testParse() {
			$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
					<root></root>";

			$parser = XmlParser::fromString($xml);

			$this->assertSame($parser, $parser->parse());
		}

		public function testParse_tryAgain() {
			$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
					<root></root>";

			$parser = XmlParser::fromString($xml);

			$parser->parse();

			$this->expectException(RuntimeException::class);

			$parser->parse();
		}

		public function testConsume() {
			$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
					<root>
						<el>d<b>15</b>c</el>
					</root>";

			$parser = XmlParser::fromString($xml);

			$i = 0;

			$parser->addCallback(new ElementStartCallback(['root', 'el'], function (XmlParser $parser) use (&$i) {

				$parser->value('b', $b);

				$this->assertSame($parser, $parser->consume());

				$this->assertSame('15', $b);

				++$i;
			}));
			$parser->parse();

			$this->assertSame(1, $i);
		}

		public function testConsume_alreadyConsumed() {
			$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
					<root>
						<el>d<b>15</b>c</el>
					</root>";

			$parser = XmlParser::fromString($xml);

			$i = 0;

			$parser->addCallback(new ElementStartCallback(['root', 'el'], function (XmlParser $parser) use (&$i) {

				$parser->value('b', $b);

				$parser->consume();

				$this->expectException(RuntimeException::class);

				$parser->consume();

				++$i;
			}));
			$parser->parse();

			$this->assertSame(1, $i);
		}

		public function testRoot() {
			$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
					<rootNode><b>c</b></rootNode>";

			$parser = XmlParser::fromString($xml);

			$i = 0;

			$parser->root(function (XmlParser $p) use (&$i, $parser) {
				$this->assertSame('rootNode', $p->elName());
				$this->assertSame($parser, $p);

				++$i;
			});
			$parser->parse();

			$this->assertSame(1, $i);
		}

		public function testRoot_colonAdaptsNamespace() {
			$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
					<rootNode xmlns=\"http://mehr-it.info/EasyXmlReaderTest\">
						<b>c</b>
					</rootNode>";

			$parser = XmlParser::fromString($xml);

			$this->assertSame($parser, $parser->root(function (XmlParser $parser) use (&$b) {
				$parser->value(':b', $b);
			}));
			$parser->parse();

			$this->assertSame('c', $b);
		}

		public function testFirst() {
			$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
					<rootNode>
						<item><b>15</b><d>25</d></item>
						<item><b>16</b><d>26</d></item>
						<item><b>17</b><d>27</d></item>
						<item2><b>18</b><d>28</d></item2>
					</rootNode>";

			$parser = XmlParser::fromString($xml);

			$i = 0;
			$this->assertSame($parser, $parser->first('rootNode.item', function (XmlParser $parser) use (&$i) {
				++$i;
				$parser->value('b', $v);
				$parser->consume();
				$this->assertSame('15', $v);
			}));
			$parser->parse();

			$this->assertSame(1, $i);
		}

		public function testEach() {
			$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
					<rootNode>
						<item><b>15</b><d>25</d></item>
						<item><b>16</b><d>26</d></item>
						<item><b>17</b><d>27</d></item>
						<item2><b>18</b><d>28</d></item2>
					</rootNode>";

			$parser = XmlParser::fromString($xml);

			$i = 0;
			$this->assertSame($parser, $parser->each('rootNode.item', function (XmlParser $parser) use (&$i) {
				switch ($i++) {
					case 0:
						$parser->value('b', $v);
						$parser->consume();
						$this->assertSame('15', $v);
						break;

					case 1:
						$parser->value('b', $v);
						$parser->consume();
						$this->assertSame('16', $v);
						break;

					case 2:
						$parser->value('b', $v);
						$parser->consume();
						$this->assertSame('17', $v);

				}
			}));
			$parser->parse();

			$this->assertSame(3, $i);
		}

		public function testEach_isRelativeToCurrent() {
			$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
					<rootNode>
						<item><b>15</b><d>25</d></item>
						<item><b>16</b><d>26</d></item>
						<item><b>17</b><d>27</d></item>
						<item2><b>18</b><d>28</d></item2>
					</rootNode>";

			$parser = XmlParser::fromString($xml);

			$i = 0;
			$parser->root(function (XmlParser $parser) use (&$i) {
				$this->assertSame($parser, $parser->each('item', function (XmlParser $parser) use (&$i) {
					switch ($i++) {
						case 0:
							$parser->value('b', $v);
							$parser->consume();
							$this->assertSame('15', $v);
							break;

						case 1:
							$parser->value('b', $v);
							$parser->consume();
							$this->assertSame('16', $v);
							break;

						case 2:
							$parser->value('b', $v);
							$parser->consume();
							$this->assertSame('17', $v);

					}
				}));
			});
			$parser->parse();

			$this->assertSame(3, $i);
		}

		public function testEach_filtersByNamespace() {
			$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
					<rootNode>
						<item xmlns=\"http://mehr-it.info/EasyXmlReaderTest2\"><b>15</b><d>25</d></item>
						<item xmlns=\"http://mehr-it.info/EasyXmlReaderTest2\"><b>16</b><d>26</d></item>
						<item xmlns=\"http://mehr-it.info/EasyXmlReaderTest2\"><b>17</b><d>27</d></item>
						<item><b>18</b><d>28</d></item>
					</rootNode>";

			$parser = XmlParser::fromString($xml);

			$i = 0;
			$this->assertSame($parser, $parser->each('rootNode.{http://mehr-it.info/EasyXmlReaderTest2}item', function (XmlParser $parser) use (&$i) {
				switch ($i++) {
					case 0:
						$parser->value(':b', $v);
						$parser->consume();
						$this->assertSame('15', $v);
						break;

					case 1:
						$parser->value(':b', $v);
						$parser->consume();
						$this->assertSame('16', $v);
						break;

					case 2:
						$parser->value(':b', $v);
						$parser->consume();
						$this->assertSame('17', $v);

				}
			}));
			$parser->parse();

			$this->assertSame(3, $i);
		}

		public function testEach_filtersByNamespace_prefixed() {
			$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
					<rootNode>
						<item xmlns=\"http://mehr-it.info/EasyXmlReaderTest2\"><b>15</b><d>25</d></item>
						<item xmlns=\"http://mehr-it.info/EasyXmlReaderTest2\"><b>16</b><d>26</d></item>
						<item xmlns=\"http://mehr-it.info/EasyXmlReaderTest2\"><b>17</b><d>27</d></item>
						<item><b>18</b><d>28</d></item>
					</rootNode>";

			$parser = XmlParser::fromString($xml);
			$parser->prefix('a', 'http://mehr-it.info/EasyXmlReaderTest2');

			$i = 0;
			$this->assertSame($parser, $parser->each('rootNode.a:item', function (XmlParser $parser) use (&$i) {
				switch ($i++) {
					case 0:
						$parser->value(':b', $v);
						$parser->consume();
						$this->assertSame('15', $v);
						break;

					case 1:
						$parser->value(':b', $v);
						$parser->consume();
						$this->assertSame('16', $v);
						break;

					case 2:
						$parser->value(':b', $v);
						$parser->consume();
						$this->assertSame('17', $v);

				}
			}));

			$parser->parse();

			$this->assertSame(3, $i);
		}

		public function testEachValue() {
			$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
					<rootNode>
						<item>1a<sub>x</sub>b<![CDATA[c]]><!--z-->d</item>
						<item>2a<sub>x</sub>b<![CDATA[c]]><!--z-->d</item>
						<item2>3a<sub>x</sub>b<![CDATA[c]]><!--z-->d</item2>
					</rootNode>";

			$parser = XmlParser::fromString($xml);

			$i = 0;
			$this->assertSame($parser, $parser->eachValue('rootNode.item', function ($v) use (&$i) {
				switch ($i++) {
					case 0:
						$this->assertSame('1abcd', $v);
						break;
					case 1:
						$this->assertSame('2abcd', $v);
						break;

				}
			}));
			$parser->parse();

			$this->assertSame(2, $i);
		}

		public function testEachValue_isRelativeToCurrent() {
			$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
					<rootNode>
						<item>1a<sub>x</sub>b<![CDATA[c]]><!--z-->d</item>
						<item>2a<sub>x</sub>b<![CDATA[c]]><!--z-->d</item>
						<item2>3a<sub>x</sub>b<![CDATA[c]]><!--z-->d</item2>
					</rootNode>";

			$parser = XmlParser::fromString($xml);

			$i = 0;
			$parser->root(function (XmlParser $parser) use (&$i) {
				$this->assertSame($parser, $parser->eachValue('item', function ($v) use (&$i) {
					switch ($i++) {
						case 0:
							$this->assertSame('1abcd', $v);
							break;
						case 1:
							$this->assertSame('2abcd', $v);
							break;

					}
				}));
			});
			$parser->parse();

			$this->assertSame(2, $i);
		}

		public function testEachValue_filtersByNamespace() {
			$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
					<rootNode>
						<item xmlns=\"http://mehr-it.info/EasyXmlReaderTest2\">1a<sub>x</sub>b<![CDATA[c]]><!--z-->d</item>
						<item xmlns=\"http://mehr-it.info/EasyXmlReaderTest2\">2a<sub>x</sub>b<![CDATA[c]]><!--z-->d</item>
						<item>3a<sub>x</sub>b<![CDATA[c]]><!--z-->d</item>
					</rootNode>";

			$parser = XmlParser::fromString($xml);

			$i = 0;
			$this->assertSame($parser, $parser->eachValue('rootNode.{http://mehr-it.info/EasyXmlReaderTest2}item', function ($v) use (&$i) {
				switch ($i++) {
					case 0:
						$this->assertSame('1abcd', $v);
						break;
					case 1:
						$this->assertSame('2abcd', $v);
						break;

				}
			}));

			$parser->parse();

			$this->assertSame(2, $i);
		}

		public function testEachValue_filtersByNamespace_prefixed() {
			$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
					<rootNode>
						<item xmlns=\"http://mehr-it.info/EasyXmlReaderTest2\">1a<sub>x</sub>b<![CDATA[c]]><!--z-->d</item>
						<item xmlns=\"http://mehr-it.info/EasyXmlReaderTest2\">2a<sub>x</sub>b<![CDATA[c]]><!--z-->d</item>
						<item>3a<sub>x</sub>b<![CDATA[c]]><!--z-->d</item>
					</rootNode>";

			$parser = XmlParser::fromString($xml);

			$i = 0;
			$parser->prefix('a', 'http://mehr-it.info/EasyXmlReaderTest2');
			$this->assertSame($parser, $parser->eachValue('rootNode.a:item', function ($v) use (&$i) {
				switch ($i++) {
					case 0:
						$this->assertSame('1abcd', $v);
						break;
					case 1:
						$this->assertSame('2abcd', $v);
						break;

				}
			}));

			$parser->parse();

			$this->assertSame(2, $i);
		}

		public function testCollectValue() {
			$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
					<rootNode>
						<item>1a<sub>x</sub>b<![CDATA[c]]><!--z-->d</item>
						<item>2a<sub>x</sub>b<![CDATA[c]]><!--z-->d</item>
						<item2>3a<sub>x</sub>b<![CDATA[c]]><!--z-->d</item2>
					</rootNode>";

			$parser = XmlParser::fromString($xml);

			$this->assertSame($parser, $parser->collectValue('rootNode.item', $values));
			$parser->parse();

			$this->assertSame(['1abcd', '2abcd'], $values);
		}

		public function testCollectValue_default() {
			$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
					<rootNode>
						<item>a</item>
						<item></item>
						<item/>
						<item2>c</item2>
					</rootNode>";

			$parser = XmlParser::fromString($xml);

			$this->assertSame($parser, $parser->collectValue('rootNode.item', $values, 'default:x'));
			$parser->parse();

			$this->assertSame(['a', 'x', 'x'], $values);
		}

		public function testCollectValue_defaultNull() {
			$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
					<rootNode>
						<item>a</item>
						<item></item>
						<item/>
						<item2>c</item2>
					</rootNode>";

			$parser = XmlParser::fromString($xml);

			$this->assertSame($parser, $parser->collectValue('rootNode.item', $values, 'defaultNull'));
			$parser->parse();

			$this->assertSame(['a', null, null], $values);
		}

		public function testCollectValue_trim() {
			$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
					<rootNode>
						<item> a 
						</item>
						<item> b 
						</item>
						<item2> c 
						</item2>
					</rootNode>";

			$parser = XmlParser::fromString($xml);

			$this->assertSame($parser, $parser->collectValue('rootNode.item', $values, 'trim'));
			$parser->parse();

			$this->assertSame(['a', 'b'], $values);
		}

		public function testCollectValue_upper() {
			$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
					<rootNode>
						<item>a</item>
						<item>b</item>
						<item2>c</item2>
					</rootNode>";

			$parser = XmlParser::fromString($xml);

			$this->assertSame($parser, $parser->collectValue('rootNode.item', $values, 'upper'));
			$parser->parse();

			$this->assertSame(['A', 'B'], $values);
		}

		public function testCollectValue_lower() {
			$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
					<rootNode>
						<item>A</item>
						<item>B</item>
						<item2>C</item2>
					</rootNode>";

			$parser = XmlParser::fromString($xml);

			$this->assertSame($parser, $parser->collectValue('rootNode.item', $values, 'lower'));
			$parser->parse();

			$this->assertSame(['a', 'b'], $values);
		}

		public function testCollectValue_bool() {
			$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
					<rootNode>
						<item>0</item>
						<item></item>
						<item>false</item>
						<item>FALSE</item>
						<item>FALse</item>
						<item>-1</item>
						<item>1</item>
						<item>true</item>
						<item>TRUE</item>
						<item>TRue</item>
						<item2>C</item2>
					</rootNode>";

			$parser = XmlParser::fromString($xml);

			$this->assertSame($parser, $parser->collectValue('rootNode.item', $values, 'bool'));
			$parser->parse();

			$this->assertSame([false, false, false, false, false, true, true, true, true, true], $values);
		}

		public function testCollectValue_number() {
			$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
					<rootNode>
						<item>0</item>
						<item></item>
						<item>E</item>
						<item>1e10</item>
						<item>-15</item>
						<item>15</item>
						<item>-15.78</item>
						<item>15.78</item>
					</rootNode>";

			$parser = XmlParser::fromString($xml);

			$this->assertSame($parser, $parser->collectValue('rootNode.item', $values, 'number'));
			$parser->parse();

			$this->assertSame(['0', null, null, null, '-15', '15', '-15.78', '15.78'], $values);
		}

		public function testCollectValue_number_decSep() {
			$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
					<rootNode>
						<item>0</item>
						<item></item>
						<item>E</item>
						<item>1e10</item>
						<item>-15</item>
						<item>15</item>
						<item>-15,78</item>
						<item>15,78</item>
					</rootNode>";

			$parser = XmlParser::fromString($xml);

			$this->assertSame($parser, $parser->collectValue('rootNode.item', $values, 'number:,'));
			$parser->parse();

			$this->assertSame(['0', null, null, null, '-15', '15', '-15.78', '15.78'], $values);
		}

		public function testCollectValue_number_defaultDecimalSep() {
			$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
					<rootNode>
						<item>0</item>
						<item></item>
						<item>E</item>
						<item>1e10</item>
						<item>-15</item>
						<item>15</item>
						<item>-15,78</item>
						<item>15,78</item>
					</rootNode>";

			$parser = XmlParser::fromString($xml);


			$this->assertSame($parser, $parser->setDefaultDecimalSeparator(','));
			$this->assertSame($parser, $parser->collectValue('rootNode.item', $values, 'number'));
			$parser->parse();

			$this->assertSame(['0', null, null, null, '-15', '15', '-15.78', '15.78'], $values);
		}

		public function testCollectValue_int() {
			$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
					<rootNode>
						<item>0</item>
						<item></item>
						<item>E</item>
						<item>1e10</item>
						<item>-15</item>
						<item>15</item>
						<item>-15.78</item>
						<item>15.78</item>
					</rootNode>";

			$parser = XmlParser::fromString($xml);

			$this->assertSame($parser, $parser->collectValue('rootNode.item', $values, 'int'));
			$parser->parse();

			$this->assertSame(['0', null, null, null, '-15', '15', null, null], $values);
		}

		public function testCollectValue_date() {

			$tzOffset = (new \DateTime('2019-01-06T10:21:00'))->format('P');

			if ($tzOffset == '+00:00')
				$this->markTestSkipped('Test must nut be run in UTC timezone');

			$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
					<rootNode>
						<item></item>
						<item>asd</item>
						<item>2019-01-06T10:21:00Z</item>
						<item>2019-01-06T10:21:00</item>
						<item>2019-01-06Z</item>
					</rootNode>";

			$parser = XmlParser::fromString($xml);

			$this->assertSame($parser, $parser->collectValue('rootNode.item', $values, 'date'));
			$parser->parse();

			$this->assertSame(null, $values[0]);
			$this->assertSame(null, $values[1]);
			$this->assertSame('2019-01-06T10:21:00+00:00', $values[2]->format('c'));
			$this->assertSame("2019-01-06T10:21:00$tzOffset", $values[3]->format('c'));
			$this->assertSame("2019-01-06T00:00:00+00:00", $values[4]->format('c'));
		}

		public function testCollectValue_date_withTimezone() {

			$tzOffset = (new \DateTime('2019-01-06T10:21:00'))->format('P');

			if ($tzOffset == '+00:00')
				$this->markTestSkipped('Test must nut be run in UTC timezone');

			$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
					<rootNode>
						<item></item>
						<item>asd</item>
						<item>2019-01-06T10:21:00Z</item>
						<item>2019-01-06T10:21:00</item>
						<item>2019-01-06Z</item>
					</rootNode>";

			$parser = XmlParser::fromString($xml);

			$this->assertSame($parser, $parser->collectValue('rootNode.item', $values, 'date:Europe/Berlin'));
			$parser->parse();

			$this->assertSame(null, $values[0]);
			$this->assertSame(null, $values[1]);
			$this->assertSame('2019-01-06T10:21:00+00:00', $values[2]->format('c'));
			$this->assertSame("2019-01-06T10:21:00+01:00", $values[3]->format('c'));
			$this->assertSame("2019-01-06T00:00:00+00:00", $values[4]->format('c'));
		}

		public function testCollectValue_customConverter() {


			$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
					<rootNode>
						<item>1</item>
						<item>b</item>
					</rootNode>";

			$parser = XmlParser::fromString($xml);

			$this->assertSame($parser, $parser->addConverter('my_handler', function ($x) {
				return $x . ':suffix';
			}));

			$this->assertSame($parser, $parser->collectValue('rootNode.item', $values, 'my_handler'));
			$parser->parse();

			$this->assertSame('1:suffix', $values[0]);
			$this->assertSame('b:suffix', $values[1]);
		}

		public function testCollectValue_customConverter_withArguments() {


			$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
					<rootNode>
						<item>1</item>
						<item>b</item>
					</rootNode>";

			$parser = XmlParser::fromString($xml);

			$this->assertSame($parser, $parser->addConverter('my_handler', function ($x, $y, $z) {
				return "$x,$y,$z";
			}));

			$this->assertSame($parser, $parser->collectValue('rootNode.item', $values, 'my_handler:a:b'));
			$parser->parse();

			$this->assertSame('1,a,b', $values[0]);
			$this->assertSame('b,a,b', $values[1]);
		}

		public function testCollectValue_closure() {
			$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
					<rootNode>
						<item>a</item>
						<item>b</item>
						<item></item>
					</rootNode>";

			$parser = XmlParser::fromString($xml);

			$this->assertSame($parser, $parser->collectValue('rootNode.item', $values, function ($v) {
				return $v . '0';
			}));
			$parser->parse();

			$this->assertSame(['a0', 'b0', '0'], $values);
		}

		public function testCollectValue_multiple() {
			$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
					<rootNode>
						<item> a 
						</item>
						<item> b 
						</item>
						<item></item>
					</rootNode>";

			$parser = XmlParser::fromString($xml);

			$this->assertSame($parser, $parser->collectValue('rootNode.item', $values, 'default: c|trim|upper'));
			$parser->parse();

			$this->assertSame(['A', 'B', 'C'], $values);
		}

		public function testCollectValue_multipleByArray() {
			$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
					<rootNode>
						<item> a 
						</item>
						<item> b 
						</item>
						<item></item>
					</rootNode>";

			$parser = XmlParser::fromString($xml);

			$this->assertSame($parser, $parser->collectValue('rootNode.item', $values, ['default: c', 'trim', function ($v) {
				return strtoupper($v);
			}
			]));
			$parser->parse();

			$this->assertSame(['A', 'B', 'C'], $values);
		}

		public function testValue() {
			$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
					<rootNode>
						<item2>3a<sub>x</sub>b<![CDATA[c]]><!--z-->d</item2>
						<item>1a<sub>x</sub>b<![CDATA[c]]><!--z-->d</item>
						<item>2a<sub>x</sub>b<![CDATA[c]]><!--z-->d</item>
					</rootNode>";

			$parser = XmlParser::fromString($xml);

			$this->assertSame($parser, $parser->value('rootNode.item', $value));
			$parser->parse();

			$this->assertSame('1abcd', $value);
		}

		public function testValue_trim() {
			$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
					<rootNode>
						<item2> c </item2>
						<item> a </item>
						<item> b </item>
					</rootNode>";

			$parser = XmlParser::fromString($xml);

			$this->assertSame($parser, $parser->value('rootNode.item', $value, 'trim'));
			$parser->parse();

			$this->assertSame('a', $value);
		}

		public function testValueCurrentElement() {
			$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
					<rootNode>
						<item>1a<sub>x</sub>b<![CDATA[c]]><!--z-->d</item>
						<item>2a<sub>x</sub>b<![CDATA[c]]><!--z-->d</item>
					</rootNode>";

			$parser = XmlParser::fromString($xml);

			$out = null;
			$i = 0;
			$parser->each('rootNode.item', function(XmlParser $parser) use (&$out, &$i) {
				$parser->value('', $out);

				switch($i) {
					case 1:
						$this->assertEquals('1abcd', $out);
				}

				++$i;
			});

			$parser->parse();

			$this->assertEquals('2abcd', $out);
		}

		public function testValueCurrentElementSelfClosing() {
			$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
					<rootNode>
						<item/>
					</rootNode>";

			$parser = XmlParser::fromString($xml);

			$out = 'asd';
			$i = 0;
			$parser->each('rootNode.item', function(XmlParser $parser) use (&$out, &$i) {
				$parser->value('', $out);

				switch($i) {
					case 1:
						$this->assertEquals(null, $out);
				}

				++$i;
			});

			$parser->parse();

			$this->assertEquals(null, $out);
		}

		public function testCollectAttribute() {
			$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
					<rootNode>
						<item a=\"19\"></item>
						<item a=\"20\"></item>
						<item a=\"\"></item>
						<item></item>
						<item2></item2>
					</rootNode>";

			$parser = XmlParser::fromString($xml);

			$this->assertSame($parser, $parser->collectAttribute('rootNode.item', 'a', $values));
			$parser->parse();

			$this->assertSame(['19', '20', '', null], $values);
		}

		public function testCollectAttribute_namespaced() {
			$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
					<rootNode xmlns:n=\"http://mehr-it.info/EasyXmlTestNamespace\">
						<item n:a=\"19\"></item>
						<item n:a=\"20\"></item>
						<item n:a=\"\"></item>
						<item a=\"\"></item>
						<item></item>
						<item2></item2>
					</rootNode>";

			$parser = XmlParser::fromString($xml);

			$this->assertSame($parser, $parser->collectAttribute('rootNode.item', '{http://mehr-it.info/EasyXmlTestNamespace}a', $values));
			$parser->parse();

			$this->assertSame(['19', '20', '', null, null], $values);
		}

		public function testCollectAttribute_namespaced_prefix() {
			$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
					<rootNode xmlns:n=\"http://mehr-it.info/EasyXmlTestNamespace\">
						<item n:a=\"19\"></item>
						<item n:a=\"20\"></item>
						<item n:a=\"\"></item>
						<item a=\"\"></item>
						<item></item>
						<item2></item2>
					</rootNode>";

			$parser = XmlParser::fromString($xml);
			$parser->prefix('ns', 'http://mehr-it.info/EasyXmlTestNamespace');
			$this->assertSame($parser, $parser->collectAttribute('rootNode.item', 'ns:a', $values));
			$parser->parse();

			$this->assertSame(['19', '20', '', null, null], $values);
		}

		public function testCollectAttribute_trim() {
			$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
					<rootNode>
						<item a=\" 19 \"></item>
						<item a=\" 20 \"></item>
						<item a=\" \"></item>
						<item></item>
						<item2></item2>
					</rootNode>";

			$parser = XmlParser::fromString($xml);

			$this->assertSame($parser, $parser->collectAttribute('rootNode.item', 'a', $values, 'trim'));
			$parser->parse();

			$this->assertSame(['19', '20', '', null], $values);
		}

		public function testAttribute() {
			$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
					<rootNode>
						<item a=\"19\"></item>
						<item a=\"20\"></item>
					</rootNode>";

			$parser = XmlParser::fromString($xml);

			$this->assertSame($parser, $parser->attribute('rootNode.item', 'a', $value));
			$parser->parse();

			$this->assertSame('19', $value);
		}

		public function testAttribute_namespaced() {
			$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
					<rootNode xmlns:n=\"http://mehr-it.info/EasyXmlTestNamespace\">
						<item a=\"19\"></item>
						<item n:a=\"20\"></item>
						<item n:a=\"18\"></item>
					</rootNode>";

			$parser = XmlParser::fromString($xml);

			$this->assertSame($parser, $parser->attribute('rootNode.item', '{http://mehr-it.info/EasyXmlTestNamespace}a', $value));
			$parser->parse();

			$this->assertSame('20', $value);
		}

		public function testAttribute_namespaced_prefix() {
			$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
					<rootNode xmlns:n=\"http://mehr-it.info/EasyXmlTestNamespace\">
						<item a=\"19\"></item>
						<item n:a=\"20\"></item>
						<item n:a=\"18\"></item>
					</rootNode>";

			$parser = XmlParser::fromString($xml);
			$parser->prefix('ns', 'http://mehr-it.info/EasyXmlTestNamespace');
			$this->assertSame($parser, $parser->attribute('rootNode.item', 'ns:a', $value));
			$parser->parse();

			$this->assertSame('20', $value);
		}


		public function testAttribute_trim() {
			$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
					<rootNode>
						<item a=\"19\"></item>
						<item a=\" 20 \"></item>
					</rootNode>";

			$parser = XmlParser::fromString($xml);

			$this->assertSame($parser, $parser->attribute('rootNode.item', 'a', $value, 'trim'));
			$parser->parse();

			$this->assertSame('19', $value);
		}

		public function testUnserialize_closure() {
			$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
					<rootNode>
						<item><sub>a</sub></item>
						<item><sub>b</sub></item>
					</rootNode>";

			$parser = XmlParser::fromString($xml);

			$expValue = new \stdClass();

			$this->assertSame($parser, $parser->unserialize('rootNode.item', function($p) use ($parser, $expValue) {
				$this->assertSame($parser, $p);

				$expValue->x = null;
				$parser->value('sub', $expValue->x);

				return $expValue;

			}, $value));
			$parser->parse();

			$this->assertSame($expValue, $value);
			$this->assertSame('a', $value->x);
		}

		public function testUnserialize_unserializable() {
			$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
					<rootNode>
						<item><sub>a</sub></item>
						<item><sub>b</sub></item>
					</rootNode>";

			$parser = XmlParser::fromString($xml);

			$uns = new XmlParserTestUnserializable();

			$this->assertSame($parser, $parser->unserialize('rootNode.item', $uns, $value));
			$parser->parse();

			$this->assertSame($uns, $value);
			$this->assertSame('a', $value->value);
		}

		public function testUnserializeAll_closure() {
			$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
					<rootNode>
						<item><sub>a</sub></item>
						<item><sub>b</sub></item>
					</rootNode>";

			$parser = XmlParser::fromString($xml);


			$this->assertSame($parser, $parser->unserializeAll('rootNode.item', function ($p) use ($parser) {
				$this->assertSame($parser, $p);

				$ret = new \stdClass();
				$ret->x = null;
				$parser->value('sub', $ret->x);

				return $ret;

			}, $values));
			$parser->parse();

			$this->assertInstanceOf(\stdClass::class, $values[0]);
			$this->assertSame('a', $values[0]->x);

			$this->assertInstanceOf(\stdClass::class, $values[1]);
			$this->assertSame('b', $values[1]->x);

			$this->assertCount(2, $values);
		}

		public function testUnserializeAll_unserializable() {
			$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
					<rootNode>
						<item><sub>a</sub></item>
						<item><sub>b</sub></item>
					</rootNode>";

			$parser = XmlParser::fromString($xml);


			$uns = new XmlParserTestUnserializable();

			$this->assertSame($parser, $parser->unserializeAll('rootNode.item', $uns, $values));
			$parser->parse();

			$this->assertInstanceOf(XmlParserTestUnserializable::class, $values[0]);
			$this->assertSame('a', $values[0]->value);

			$this->assertInstanceOf(XmlParserTestUnserializable::class, $values[1]);
			$this->assertSame('b', $values[1]->value);

			$this->assertCount(2, $values);
		}


	}


	class XmlParserTestElementEndCallback extends AbstractCallback {

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
				\XMLReader::END_ELEMENT => true,
			];
		}

		/**
		 * @inheritDoc
		 */
		public function handle(XmlParser $parser) {

			call_user_func($this->handler, $parser);

		}

	}

	class XmlParserTestElementStartTextEndCallback extends AbstractCallback {

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
				\XMLReader::END_ELEMENT => true,
				\XMLReader::ELEMENT => true,
				\XMLReader::TEXT => true,
				\XMLReader::CDATA => true,
			];
		}

		/**
		 * @inheritDoc
		 */
		public function handle(XmlParser $parser) {

			call_user_func($this->handler, $parser);

		}

	}

	class XmlParserTestUnserializable implements XmlUnserialize {

		public $value;

		/**
		 * @inheritDoc
		 */
		public function xmlUnserialize(XmlParser $parser) {
			$parser->value('sub', $this->value);
		}


	}