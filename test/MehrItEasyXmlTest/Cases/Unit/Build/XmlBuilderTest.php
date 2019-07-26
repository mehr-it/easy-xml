<?php
	/**
	 * Created by PhpStorm.
	 * User: chris
	 * Date: 05.03.19
	 * Time: 22:48
	 */

	namespace MehrItEasyXmlTest\Cases\Unit\Build;


	use MehrIt\EasyXml\Contracts\XmlSerializable;
	use MehrIt\EasyXml\Exception\XmlException;
	use MehrIt\EasyXml\Build\XmlBuilder;
	use PHPUnit\Framework\TestCase;

	class XmlBuilderTest extends TestCase
	{
		protected $docStart = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";

		public function testStartDocument() {

			$builder = new XmlBuilder();

			$this->assertSame($builder, $builder->startDocument());

			$this->assertSame("<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n", $builder->output());
		}

		public function testStartDocumentWithEncoding() {

			$builder = new XmlBuilder();

			$this->assertSame($builder, $builder->startDocument('1.1', 'ISO-8859-1'));

			$this->assertSame("<?xml version=\"1.1\" encoding=\"ISO-8859-1\"?>\n", $builder->output());
		}

		public function testStartDocumentWithStandalone() {

			$builder = new XmlBuilder();

			$this->assertSame($builder, $builder->startDocument('1.1', 'ISO-8859-1', 'yes'));

			$this->assertSame("<?xml version=\"1.1\" encoding=\"ISO-8859-1\" standalone=\"yes\"?>\n", $builder->output());
		}

		public function testStartDocumentAlreadyStarted() {

			$builder = new XmlBuilder();

			$this->assertSame($builder, $builder->startDocument());

			$this->expectException(XmlException::class);

			$builder->startDocument();
		}

		public function testEndDocument() {
			$builder = new XmlBuilder();

			$builder->startDocument();

			$this->assertSame($builder, $builder->endDocument());
		}

		public function testEndDocumentNotStarted() {
			$builder = new XmlBuilder();

			$this->expectException(XmlException::class);

			$builder->endDocument();
		}

		public function testStartElement() {
			$builder = new XmlBuilder();

			$builder->startDocument();
			$this->assertSame($builder, $builder->startElement('myTag'));

			$this->assertSame("{$this->docStart}<myTag", $builder->output());
		}

		public function testStartElementWithAttributes() {
			$builder = new XmlBuilder();

			$builder->startDocument();
			$this->assertSame($builder, $builder->startElement('myTag', ['myAttr' => 'myVal']));

			$this->assertSame("{$this->docStart}<myTag myAttr=\"myVal\"", $builder->output());
		}

		public function testStartElementNested() {
			$builder = new XmlBuilder();

			$builder->startDocument();
			$this->assertSame($builder, $builder->startElement('myTag'));
			$this->assertSame($builder, $builder->startElement('anotherTag'));

			$this->assertSame("{$this->docStart}<myTag><anotherTag", $builder->output());
		}

		public function testStartElementNamespace() {
			$builder = new XmlBuilder();

			$builder->startDocument();
			$this->assertSame($builder, $builder->startElement('{http://mynamespace.de/xml}myTag'));
			$builder->endElement();

			$this->assertSame("{$this->docStart}<a:myTag xmlns:a=\"http://mynamespace.de/xml\"/>", $builder->output());
		}

		public function testStartElementNamespace_attributeDefinesNamespace() {
			$builder = new XmlBuilder();

			$builder->startDocument();
			$this->assertSame($builder, $builder->startElement('{http://mynamespace.de/xml}myTag', ['xmlns:z' => 'http://mynamespace.de/xml']));
			$builder->endElement();

			$this->assertSame("{$this->docStart}<z:myTag xmlns:z=\"http://mynamespace.de/xml\"/>", $builder->output());
		}

		public function testStartElementNamespace_parentDefinesPrefix() {
			$builder = new XmlBuilder();

			$builder->startDocument();
			$this->assertSame($builder, $builder->startElement('root', ['xmlns:b' => 'http://mynamespace.de/xml']));
			$this->assertSame($builder, $builder->startElement('b:myTag'));
			$builder->endElement();

			$this->assertSame("{$this->docStart}<root xmlns:b=\"http://mynamespace.de/xml\"><b:myTag/>", $builder->output());
		}

		public function testStartElementNamespace_siblingDefinesNamespace() {
			$builder = new XmlBuilder();

			$builder->startDocument();
			$builder->startElement('root');
			$this->assertSame($builder, $builder->startElement('{http://mynamespace.de/xml}myTag'));
			$builder->endElement();
			$this->assertSame($builder, $builder->startElement('{http://mynamespace.de/xml}myTag'));
			$builder->endElement();

			$this->assertSame("{$this->docStart}<root><a:myTag xmlns:a=\"http://mynamespace.de/xml\"/><a:myTag xmlns:a=\"http://mynamespace.de/xml\"/>", $builder->output());
		}

		public function testStartElementNamespace_parentDefinesOtherNamespace() {
			$builder = new XmlBuilder();

			$builder->startDocument();
			$this->assertSame($builder, $builder->startElement('a:root', ['xmlns:a' => 'http://mynamespace.de/xml']));
			$this->assertSame($builder, $builder->startElement('{http://anotherNamespace.de/xml}myTag'));
			$builder->endElement();

			$this->assertSame("{$this->docStart}<a:root xmlns:a=\"http://mynamespace.de/xml\"><b:myTag xmlns:b=\"http://anotherNamespace.de/xml\"/>", $builder->output());
		}

		public function testStartElementNamespace_parentDefinesSameNamespace() {
			$builder = new XmlBuilder();

			$builder->startDocument();
			$this->assertSame($builder, $builder->startElement('a:root', ['xmlns:a' => 'http://mynamespace.de/xml']));
			$this->assertSame($builder, $builder->startElement('{http://mynamespace.de/xml}myTag'));
			$builder->endElement();

			$this->assertSame("{$this->docStart}<a:root xmlns:a=\"http://mynamespace.de/xml\"><a:myTag/>", $builder->output());
		}

		public function testStartElementNamespace_parentInSameXmlns() {
			$builder = new XmlBuilder();

			$builder->startDocument();
			$this->assertSame($builder, $builder->startElement('root', ['xmlns' => 'http://mynamespace.de/xml']));
			$this->assertSame($builder, $builder->startElement('myTag', ['xmlns' => 'http://mynamespace.de/xml']));
			$builder->endElement();

			$this->assertSame("{$this->docStart}<root xmlns=\"http://mynamespace.de/xml\"><myTag/>", $builder->output());
		}

		public function testStartElementNamespace_redefinePrefix() {
			$builder = new XmlBuilder();

			$builder->startDocument();
			$this->assertSame($builder, $builder->startElement('a:root', ['xmlns:a' => 'http://mynamespace.de/xml']));
			$this->assertSame($builder, $builder->startElement('a:myTag', ['xmlns:a' => 'http://anotherNamespace.de/xml']));
			$builder->endElement();
			$this->assertSame($builder, $builder->startElement('{http://mynamespace.de/xml}anotherTag'));

			$this->assertSame("{$this->docStart}<a:root xmlns:a=\"http://mynamespace.de/xml\"><a:myTag xmlns:a=\"http://anotherNamespace.de/xml\"/><a:anotherTag", $builder->output());
		}

		public function testStartElementNamespace_attributeWithSameNamespace() {
			$builder = new XmlBuilder();

			$builder->startDocument();
			$this->assertSame($builder, $builder->startElement('{http://mynamespace.de/xml}myTag', ['{http://mynamespace.de/xml}attr' => '15']));
			$builder->endElement();

			$this->assertSame("{$this->docStart}<a:myTag a:attr=\"15\" xmlns:a=\"http://mynamespace.de/xml\"/>", $builder->output());
		}

		public function testStartElementNamespace_attributeWithSameNamespaceDefinedByAttribute() {
			$builder = new XmlBuilder();

			$builder->startDocument();
			$this->assertSame($builder, $builder->startElement('z:myTag', [ 'xmlns:z' => 'http://mynamespace.de/xml', '{http://mynamespace.de/xml}attr' => '15']));
			$builder->endElement();

			$this->assertSame("{$this->docStart}<z:myTag xmlns:z=\"http://mynamespace.de/xml\" z:attr=\"15\"/>", $builder->output());
		}

		public function testStartElementWithoutNamespace_parentDefinesNamespace() {
			$builder = new XmlBuilder();

			$builder->startDocument();
			$this->assertSame($builder, $builder->startElement('a:root', ['xmlns:a' => 'http://mynamespace.de/xml']));
			$this->assertSame($builder, $builder->startElement('myTag'));
			$builder->endElement();

			$this->assertSame("{$this->docStart}<a:root xmlns:a=\"http://mynamespace.de/xml\"><myTag/>", $builder->output());
		}

		public function testStartElementOutsideDoc() {
			$builder = new XmlBuilder();

			$this->expectException(XmlException::class);

			$builder->startElement('myTag');
		}

		public function testStartElementAfterRootNode() {
			$builder = new XmlBuilder();

			$builder->startDocument();
			$builder->startElement('myTag');
			$builder->endElement();


			$this->expectException(XmlException::class);

			$builder->startElement('myTag');
		}

		public function testStartElementWithinAttr() {
			$builder = new XmlBuilder();

			$builder->startDocument();
			$builder->startElement('m');
			$builder->startAttribute('asd');

			$this->expectException(XmlException::class);

			$builder->startElement('myTag');
		}

		public function testEndElement() {
			$builder = new XmlBuilder();

			$builder->startDocument();
			$builder->startElement('myTag');
			$this->assertSame($builder, $builder->endElement());

			$this->assertSame("{$this->docStart}<myTag/>", $builder->output());
		}

		public function testEndElementNamespace() {
			$builder = new XmlBuilder();

			$builder->startDocument();
			$builder->startElement('{http://mynamespace.de/xml}myTag');
			$this->assertSame($builder, $builder->endElement());

			$this->assertSame("{$this->docStart}<a:myTag xmlns:a=\"http://mynamespace.de/xml\"/>", $builder->output());
		}

		public function testEndElementNested() {
			$builder = new XmlBuilder();

			$builder->startDocument();
			$builder->startElement('myTag');
			$builder->startElement('anotherTag');
			$this->assertSame($builder, $builder->endElement());
			$this->assertSame($builder, $builder->endElement());

			$this->assertSame("{$this->docStart}<myTag><anotherTag/></myTag>", $builder->output());
		}

		public function testEndElementNestedNamespace() {
			$builder = new XmlBuilder();

			$builder->startDocument();
			$builder->startElement('{http://mynamespace.de/xml}myTag');
			$builder->startElement('{http://mynamespace.de/xml}anotherTag');
			$this->assertSame($builder, $builder->endElement());
			$this->assertSame($builder, $builder->endElement());

			$this->assertSame("{$this->docStart}<a:myTag xmlns:a=\"http://mynamespace.de/xml\"><a:anotherTag/></a:myTag>", $builder->output());
		}

		public function testEndElementForceEndTag() {
			$builder = new XmlBuilder();

			$builder->startDocument();
			$builder->startElement('myTag');
			$this->assertSame($builder, $builder->endElement(true));

			$this->assertSame("{$this->docStart}<myTag></myTag>", $builder->output());
		}

		public function testEndElementForceEndTagNamespace() {
			$builder = new XmlBuilder();

			$builder->startDocument();
			$builder->startElement('{http://mynamespace.de/xml}myTag');
			$this->assertSame($builder, $builder->endElement(true));

			$this->assertSame("{$this->docStart}<a:myTag xmlns:a=\"http://mynamespace.de/xml\"></a:myTag>", $builder->output());
		}

		public function testEndElementNotStarted() {
			$builder = new XmlBuilder();

			$builder->startDocument();
			$builder->startElement('myTag');
			$this->assertSame($builder, $builder->endElement());


			$this->expectException(XmlException::class);

			$builder->endElement();
		}

		public function testStartAttribute() {
			$builder = new XmlBuilder();

			$builder->startDocument();
			$builder->startElement('myTag');

			$this->assertSame($builder, $builder->startAttribute('myAttr'));

			$this->assertSame("{$this->docStart}<myTag myAttr=\"", $builder->output());
		}

		public function testStartAttributeNamespace() {
			$builder = new XmlBuilder();

			$builder->startDocument();
			$builder->startElement('myTag');

			$this->assertSame($builder, $builder->startAttribute('{http://mynamespace.de/xml}myAttr'));
			$builder->endAttribute();

			$builder->endElement();

			$this->assertSame("{$this->docStart}<myTag a:myAttr=\"\" xmlns:a=\"http://mynamespace.de/xml\"/>", $builder->output());
		}

		public function testStartAttributeNamespace_tagDefinesNamespace() {
			$builder = new XmlBuilder();

			$builder->startDocument();
			$builder->startElement('{http://mynamespace.de/xml}myTag');

			$this->assertSame($builder, $builder->startAttribute('{http://mynamespace.de/xml}myAttr'));
			$builder->endAttribute();

			$builder->endElement();

			$this->assertSame("{$this->docStart}<a:myTag a:myAttr=\"\" xmlns:a=\"http://mynamespace.de/xml\"/>", $builder->output());
		}

		public function testStartAttributeNamespace_parentTagDefinesNamespace() {
			$builder = new XmlBuilder();

			$builder->startDocument();
			$builder->startElement('{http://mynamespace.de/xml}root');
			$builder->startElement('myTag');

			$this->assertSame($builder, $builder->startAttribute('{http://mynamespace.de/xml}myAttr'));
			$builder->endAttribute();

			$builder->endElement();

			$this->assertSame("{$this->docStart}<a:root xmlns:a=\"http://mynamespace.de/xml\"><myTag a:myAttr=\"\"/>", $builder->output());
		}

		public function testStartAttributeNamespace_parentAttributeDefinesNamespace() {
			$builder = new XmlBuilder();

			$builder->startDocument();
			$builder->startElement('root', ['{http://mynamespace.de/xml}z' => '7']);
			$builder->startElement('myTag');

			$this->assertSame($builder, $builder->startAttribute('{http://mynamespace.de/xml}myAttr'));
			$builder->endAttribute();

			$builder->endElement();

			$this->assertSame("{$this->docStart}<root a:z=\"7\" xmlns:a=\"http://mynamespace.de/xml\"><myTag a:myAttr=\"\"/>", $builder->output());
		}

		public function testStartAttributeNamespace_parentAttributeDefinesNamespacePrefix() {
			$builder = new XmlBuilder();

			$builder->startDocument();
			$builder->startElement('root', ['xmlns:z' => 'http://mynamespace.de/xml']);
			$builder->startElement('myTag');

			$this->assertSame($builder, $builder->startAttribute('{http://mynamespace.de/xml}myAttr'));
			$builder->endAttribute();

			$builder->endElement();

			$this->assertSame("{$this->docStart}<root xmlns:z=\"http://mynamespace.de/xml\"><myTag z:myAttr=\"\"/>", $builder->output());
		}

		public function testStartAttributeNamespace_anotherAttributeDefinesNamespace() {
			$builder = new XmlBuilder();

			$builder->startDocument();
			$builder->startElement('myTag');

			$builder->startAttribute('{http://mynamespace.de/xml}z');
			$builder->endAttribute();

			$this->assertSame($builder, $builder->startAttribute('{http://mynamespace.de/xml}myAttr'));
			$builder->endAttribute();

			$builder->endElement();

			$this->assertSame("{$this->docStart}<myTag a:z=\"\" a:myAttr=\"\" xmlns:a=\"http://mynamespace.de/xml\"/>", $builder->output());
		}

		public function testStartAttributeNamespace_anotherAttributeDefinesPrefix() {
			$builder = new XmlBuilder();

			$builder->startDocument();
			$builder->startElement('myTag');

			$builder->startAttribute('xmlns:z');
			$builder->text('http://mynamespace.de');
			$builder->text('/xml');
			$builder->endAttribute();

			$this->assertSame($builder, $builder->startAttribute('{http://mynamespace.de/xml}myAttr'));
			$builder->endAttribute();

			$builder->endElement();

			$this->assertSame("{$this->docStart}<myTag xmlns:z=\"http://mynamespace.de/xml\" z:myAttr=\"\"/>", $builder->output());
		}

		public function testStartAttributeOutsideElement() {
			$builder = new XmlBuilder();

			$builder->startDocument();

			$this->expectException(XmlException::class);

			$builder->startAttribute('myAttr');
		}

		public function testEndAttribute() {
			$builder = new XmlBuilder();

			$builder->startDocument();
			$builder->startElement('myTag');
			$builder->startAttribute('myAttr');

			$this->assertSame($builder, $builder->endAttribute());

			$this->assertSame("{$this->docStart}<myTag myAttr=\"\"", $builder->output());
		}

		public function testEndAttributeNotStarted() {
			$builder = new XmlBuilder();

			$builder->startDocument();
			$builder->startElement('myTag');

			$this->expectException(XmlException::class);

			$builder->endAttribute();
		}

		public function testStartComment() {
			$builder = new XmlBuilder();

			$builder->startDocument();
			$builder->startElement('root');

			$this->assertSame($builder, $builder->startComment());

			$this->assertSame("{$this->docStart}<root><!--", $builder->output());

		}

		public function testStartCommentBeforeRoot() {
			$builder = new XmlBuilder();

			$builder->startDocument();

			$this->assertSame($builder, $builder->startComment());

			$this->assertSame("{$this->docStart}<!--", $builder->output());

		}

		public function testStartCommentAfterRoot() {
			$builder = new XmlBuilder();

			$builder->startDocument();
			$builder->startElement('root');
			$builder->endElement();

			$this->assertSame($builder, $builder->startComment());

			$this->assertSame("{$this->docStart}<root/><!--", $builder->output());

		}

		public function testStartCommentOutsideDoc() {
			$builder = new XmlBuilder();

			$this->expectException(XmlException::class);

			$builder->startComment();

		}

		public function testStartCommentWithinAttribute() {
			$builder = new XmlBuilder();

			$builder->startDocument();
			$builder->startElement('root');
			$builder->startAttribute('attr');

			$this->expectException(XmlException::class);

			$builder->startComment();

		}

		public function testStartCommentWithinCData() {
			$builder = new XmlBuilder();

			$builder->startDocument();
			$builder->startElement('root');
			$builder->startCData();

			$this->expectException(XmlException::class);

			$builder->startComment();

		}

		public function testStartCommentWithinComment() {
			$builder = new XmlBuilder();

			$builder->startDocument();
			$builder->startElement('root');
			$builder->startComment();

			$this->expectException(XmlException::class);

			$builder->startComment();

		}

		public function testEndComment() {
			$builder = new XmlBuilder();

			$builder->startDocument();
			$builder->startElement('root');
			$builder->startComment();

			$this->assertSame($builder, $builder->endComment());

			$this->assertSame("{$this->docStart}<root><!---->", $builder->output());
		}

		public function testEndCommentNotStarted() {
			$builder = new XmlBuilder();

			$builder->startDocument();
			$builder->startElement('root');

			$this->expectException(XmlException::class);

			$builder->endComment();
		}

		public function testStartCData() {
			$builder = new XmlBuilder();

			$builder->startDocument();
			$builder->startElement('root');

			$this->assertSame($builder, $builder->startCData());

			$this->assertSame("{$this->docStart}<root><![CDATA[", $builder->output());
		}

		public function testStartCDataOutsideDoc() {
			$builder = new XmlBuilder();

			$this->expectException(XmlException::class);

			$builder->startCData();
		}

		public function testStartCDataWithinAttribute() {
			$builder = new XmlBuilder();

			$builder->startDocument();
			$builder->startElement('root');
			$builder->startAttribute('root');

			$this->expectException(XmlException::class);

			$builder->startCData();
		}

		public function testStartCDataWithinComment() {
			$builder = new XmlBuilder();

			$builder->startDocument();
			$builder->startElement('root');
			$builder->startComment();

			$this->expectException(XmlException::class);

			$builder->startCData();
		}

		public function testStartCDataWithinCData() {
			$builder = new XmlBuilder();

			$builder->startDocument();
			$builder->startElement('root');
			$builder->startCData();

			$this->expectException(XmlException::class);

			$builder->startCData();
		}

		public function testEndCData() {
			$builder = new XmlBuilder();

			$builder->startDocument();
			$builder->startElement('root');

			$builder->startCData();

			$this->assertSame($builder, $builder->endCData());

			$this->assertSame("{$this->docStart}<root><![CDATA[]]>", $builder->output());
		}

		public function testEndCDataNotStarted() {
			$builder = new XmlBuilder();

			$builder->startDocument();
			$builder->startElement('root');

			$this->expectException(XmlException::class);

			$builder->endCData();
		}


		public function testTextWithinElement() {

			$builder = new XmlBuilder();

			$builder->startDocument();
			$builder->startElement('root');

			$this->assertSame($builder, $builder->text('my Text'));

			$this->assertSame("{$this->docStart}<root>my Text", $builder->output());
		}

		public function testTextWithinElement_escaped() {

			$builder = new XmlBuilder();

			$builder->startDocument();
			$builder->startElement('root');

			$this->assertSame($builder, $builder->text('<test>'));

			$this->assertSame("{$this->docStart}<root>&lt;test&gt;", $builder->output());
		}

		public function testTextWithinAttribute() {

			$builder = new XmlBuilder();

			$builder->startDocument();
			$builder->startElement('root');
			$builder->startAttribute('myAttr');

			$this->assertSame($builder, $builder->text('my Text'));

			$this->assertSame("{$this->docStart}<root myAttr=\"my Text", $builder->output());
		}

		public function testTextWithinAttribute_escaped() {

			$builder = new XmlBuilder();

			$builder->startDocument();
			$builder->startElement('root');
			$builder->startAttribute('myAttr');

			$this->assertSame($builder, $builder->text('">a'));

			$this->assertSame("{$this->docStart}<root myAttr=\"&quot;&gt;a", $builder->output());
		}

		public function testTextWithinComment() {

			$builder = new XmlBuilder();

			$builder->startDocument();
			$builder->startElement('root');
			$builder->startComment();

			$this->assertSame($builder, $builder->text('my Text'));

			$this->assertSame("{$this->docStart}<root><!--my Text", $builder->output());
		}

		public function testTextWithinComment_containsEndSequence() {

			$builder = new XmlBuilder();

			$builder->startDocument();
			$builder->startElement('root');
			$builder->startComment();

			$this->expectException(XmlException::class);

			$this->assertSame($builder, $builder->text('my--> Text'));

		}

		public function testTextNull() {

			$builder = new XmlBuilder();

			$builder->startDocument();
			$builder->startElement('root');

			$this->assertSame($builder, $builder->text(null));

			$this->assertSame("{$this->docStart}<root>", $builder->output());
		}

		public function testTextOutsideDocument() {

			$builder = new XmlBuilder();

			$this->expectException(XmlException::class);

			$builder->text('my Text');
		}

		public function testTextOutsideEl() {

			$builder = new XmlBuilder();

			$builder->startDocument();

			$this->expectException(XmlException::class);

			$builder->text('my Text');
		}

		public function testTextWithinCData() {

			$builder = new XmlBuilder();

			$builder->startDocument();
			$builder->startElement('root');
			$builder->startCData();

			$this->expectException(XmlException::class);

			$builder->text('my Text');
		}

		public function testData() {
			$builder = new XmlBuilder();

			$builder->startDocument();
			$builder->startElement('root');
			$builder->startCData();

			$this->assertSame($builder, $builder->data('myData'));

			$this->assertSame("{$this->docStart}<root><![CDATA[myData", $builder->output());
		}

		public function testDataContainsEndSequence() {
			$builder = new XmlBuilder();

			$builder->startDocument();
			$builder->startElement('root');
			$builder->startCData();

			$this->assertSame($builder, $builder->data('myD<![CDATA[a]]>ta'));

			$this->assertSame("{$this->docStart}<root><![CDATA[myD<![CDATA[a]]]]><![CDATA[>ta", $builder->output());
		}

		public function testDataEncodingNotChanged() {
			if (mb_internal_encoding() !== 'UTF-8')
				$this->markTestSkipped('Test requires UTF-8 as internal encoding');

			$builder = new XmlBuilder();

			$builder->startDocument();
			$builder->startElement('root');
			$builder->startCData();

			$this->assertSame($builder, $builder->data('myD' . utf8_decode('äta')));

			$this->assertSame("{$this->docStart}<root><![CDATA[myD" . utf8_decode('äta'), $builder->output());
		}

		public function testDataNull() {
			$builder = new XmlBuilder();

			$builder->startDocument();
			$builder->startElement('root');
			$builder->startCData();

			$this->assertSame($builder, $builder->data(null));

			$this->assertSame("{$this->docStart}<root><![CDATA[", $builder->output());
		}

		public function testDataOutsideDocument() {
			$builder = new XmlBuilder();

			$this->expectException(XmlException::class);

			$builder->data('myData');
		}

		public function testDataOutsideElement() {
			$builder = new XmlBuilder();

			$builder->startDocument();

			$this->expectException(XmlException::class);

			$builder->data('myData');
		}

		public function testDataWithinElement() {
			$builder = new XmlBuilder();

			$builder->startDocument();
			$builder->startElement('root');

			$this->expectException(XmlException::class);

			$builder->data('myData');
		}

		public function testDataWithinComment() {
			$builder = new XmlBuilder();

			$builder->startDocument();
			$builder->startComment();

			$this->expectException(XmlException::class);

			$builder->data('myData');
		}

		public function testDataWithinAttribute() {
			$builder = new XmlBuilder();

			$builder->startDocument();
			$builder->startElement('root');
			$builder->startAttribute('attr');

			$this->expectException(XmlException::class);

			$builder->data('myData');
		}

		public function testWriteElement() {
			$builder = new XmlBuilder();

			$builder->startDocument();

			$this->assertSame($builder, $builder->writeElement('root', 'my Text'));

			$this->assertSame("{$this->docStart}<root>my Text</root>", $builder->output());
		}

		public function testWriteElementWithAttributes() {
			$builder = new XmlBuilder();

			$builder->startDocument();

			$this->assertSame($builder, $builder->writeElement('root', 'my Text', ['z' => 15, 'c' => 0]));

			$this->assertSame("{$this->docStart}<root z=\"15\" c=\"0\">my Text</root>", $builder->output());
		}

		public function testWriteElementNamespace() {
			$builder = new XmlBuilder();

			$builder->startDocument();

			$this->assertSame($builder, $builder->writeElement('{http://mynamespace.de/xml}root', 'my Text'));

			$this->assertSame("{$this->docStart}<a:root xmlns:a=\"http://mynamespace.de/xml\">my Text</a:root>", $builder->output());
		}

		public function testWriteEmptyElement() {
			$builder = new XmlBuilder();

			$builder->startDocument();

			$this->assertSame($builder, $builder->writeElement('root', null));

			$this->assertSame("{$this->docStart}<root/>", $builder->output());
		}

		public function testWriteAttribute() {
			$builder = new XmlBuilder();

			$builder->startDocument();
			$builder->startElement('root');

			$this->assertSame($builder, $builder->writeAttribute('attr', 'value'));

			$this->assertSame("{$this->docStart}<root attr=\"value\"", $builder->output());
		}

		public function testWriteEmptyAttribute() {
			$builder = new XmlBuilder();

			$builder->startDocument();
			$builder->startElement('root');

			$this->assertSame($builder, $builder->writeAttribute('attr', null));

			$this->assertSame("{$this->docStart}<root attr=\"\"", $builder->output());
		}

		public function testWriteAttributeNamespace() {
			$builder = new XmlBuilder();

			$builder->startDocument();
			$builder->startElement('root');

			$this->assertSame($builder, $builder->writeAttribute('{http://mynamespace.de/xml}attr', 'val'));

			$builder->endElement();

			$this->assertSame("{$this->docStart}<root a:attr=\"val\" xmlns:a=\"http://mynamespace.de/xml\"/>", $builder->output());
		}

		public function testWriteAttribute_handler_date() {
			$builder = new XmlBuilder();

			$var = new \DateTime();

			$this->assertSame($builder, $builder->setAttributeHandler(\DateTime::class, function ($value, XmlBuilder $wrt) use ($var, $builder) {
				$this->assertSame($var, $value);
				$this->assertSame($builder, $wrt);

				return 'Date handled';
			}));

			$builder->startDocument();
			$builder->startElement('root');

			$this->assertSame($builder, $builder->writeAttribute('attr', $var));

			$this->assertSame("{$this->docStart}<root attr=\"Date handled\"", $builder->output());
		}

		public function testWriteAttribute_handler_integer() {
			$builder = new XmlBuilder();

			$var = 45;

			$this->assertSame($builder, $builder->setAttributeHandler('integer', function ($value, XmlBuilder $wrt) use ($var, $builder) {
				$this->assertSame($var, $value);
				$this->assertSame($builder, $wrt);

				return 'Integer handled';
			}));

			$builder->startDocument();
			$builder->startElement('root');

			$this->assertSame($builder, $builder->writeAttribute('attr', $var));

			$this->assertSame("{$this->docStart}<root attr=\"Integer handled\"", $builder->output());
		}

		public function testWriteAttribute_handler_string() {
			$builder = new XmlBuilder();

			$var = 'asd';

			$this->assertSame($builder, $builder->setAttributeHandler('string', function ($value, XmlBuilder $wrt) use ($var, $builder) {
				$this->assertSame($var, $value);
				$this->assertSame($builder, $wrt);

				return 'String handled';
			}));

			$builder->startDocument();
			$builder->startElement('root');

			$this->assertSame($builder, $builder->writeAttribute('attr', $var));

			$this->assertSame("{$this->docStart}<root attr=\"String handled\"", $builder->output());
		}

		public function testWriteAttribute_handler_float() {
			$builder = new XmlBuilder();

			$var = 45.8;

			$this->assertSame($builder, $builder->setAttributeHandler('double', function ($value, XmlBuilder $wrt) use ($var, $builder) {
				$this->assertSame($var, $value);
				$this->assertSame($builder, $wrt);

				return 'Double handled';
			}));

			$builder->startDocument();
			$builder->startElement('root');

			$this->assertSame($builder, $builder->writeAttribute('attr', $var));

			$this->assertSame("{$this->docStart}<root attr=\"Double handled\"", $builder->output());
		}

		public function testWriteAttribute_handler_null() {
			$builder = new XmlBuilder();

			$var = null;

			$this->assertSame($builder, $builder->setAttributeHandler('NULL', function ($value, XmlBuilder $wrt) use ($var, $builder) {
				$this->assertSame($var, $value);
				$this->assertSame($builder, $wrt);

				return 'Null handled';
			}));

			$builder->startDocument();
			$builder->startElement('root');

			$this->assertSame($builder, $builder->writeAttribute('attr', $var));

			$this->assertSame("{$this->docStart}<root attr=\"Null handled\"", $builder->output());
		}

		public function testWriteAttribute_handler_boolean() {
			$builder = new XmlBuilder();

			$var = false;

			$this->assertSame($builder, $builder->setAttributeHandler('boolean', function ($value, XmlBuilder $wrt) use ($var, $builder) {
				$this->assertSame($var, $value);
				$this->assertSame($builder, $wrt);

				return 'Boolean handled';
			}));

			$builder->startDocument();
			$builder->startElement('root');

			$this->assertSame($builder, $builder->writeAttribute('attr', $var));

			$this->assertSame("{$this->docStart}<root attr=\"Boolean handled\"", $builder->output());
		}


		public function testWriteAttributes() {
			$builder = new XmlBuilder();

			$builder->startDocument();
			$builder->startElement('root');

			$this->assertSame($builder, $builder->writeAttributes([
				'x' => 'b',
				'z' => 15
			]));

			$this->assertSame("{$this->docStart}<root x=\"b\" z=\"15\"", $builder->output());
		}

		public function testWriteComment() {
			$builder = new XmlBuilder();

			$builder->startDocument();
			$builder->startElement('root');

			$this->assertSame($builder, $builder->writeComment('myComment text'));

			$this->assertSame("{$this->docStart}<root><!--myComment text-->", $builder->output());
		}

		public function testWriteEmptyComment() {
			$builder = new XmlBuilder();

			$builder->startDocument();
			$builder->startElement('root');

			$this->assertSame($builder, $builder->writeComment(null));

			$this->assertSame("{$this->docStart}<root><!---->", $builder->output());
		}

		public function testWriteCData() {
			$builder = new XmlBuilder();

			$builder->startDocument();
			$builder->startElement('root');

			$this->assertSame($builder, $builder->writeCData('myData'));

			$this->assertSame("{$this->docStart}<root><![CDATA[myData]]>", $builder->output());
		}

		public function testWriteEmptyCData() {
			$builder = new XmlBuilder();

			$builder->startDocument();
			$builder->startElement('root');

			$this->assertSame($builder, $builder->writeCData(null));

			$this->assertSame("{$this->docStart}<root><![CDATA[]]>", $builder->output());
		}

		public function testWriteSystemDtd() {
			$builder = new XmlBuilder();

			$builder->startDocument();
			$this->assertSame($builder, $builder->writeSystemDtd('my-name', 'my-identifier'));

			$this->assertSame("{$this->docStart}<!DOCTYPE my-name SYSTEM \"my-identifier\">", $builder->output());
		}

		public function testWriteSystemDtd_afterRootNode() {
			$builder = new XmlBuilder();

			$builder->startDocument();
			$builder->startElement('root');
			$builder->endElement('root');

			$this->expectException(XmlException::class);

			$builder->writeSystemDtd('my-name', 'my-identifier');

		}

		public function testWritePublicDtd() {
			$builder = new XmlBuilder();

			$builder->startDocument();
			$this->assertSame($builder, $builder->writePublicDtd('my-name', 'my-public-identifier', 'my-sys-identifier'));

			$this->assertSame("{$this->docStart}<!DOCTYPE my-name PUBLIC \"my-public-identifier\" \"my-sys-identifier\">", $builder->output());
		}

		public function testWritePublicDtd_afterRootNode() {
			$builder = new XmlBuilder();

			$builder->startDocument();
			$builder->startElement('root');
			$builder->endElement('root');

			$this->expectException(XmlException::class);

			$builder->writePublicDtd('my-name', 'my-public-identifier', 'my-sys-identifier');
		}

		public function testWrite_arraySingleElement() {

			$builder = new XmlBuilder();

			$builder->startDocument();
			$this->assertSame($builder, $builder->write([
				'>' => 'myTag'
			]));

			$this->assertSame("{$this->docStart}<myTag/>", $builder->output());
		}

		public function testWrite_arraySingleElement_withAttributes() {

			$builder = new XmlBuilder();

			$builder->startDocument();
			$this->assertSame($builder, $builder->write([
				'>'  => 'myTag',
				'@b' => '15',
				'@c' => 'b',
				':b' => 'http://asd.de/xml'
			]));

			$this->assertSame("{$this->docStart}<myTag b=\"15\" c=\"b\" xmlns:b=\"http://asd.de/xml\"/>", $builder->output());
		}

		public function testWrite_arraySingleElement_withContent() {

			$builder = new XmlBuilder();

			$builder->startDocument();
			$this->assertSame($builder, $builder->write([
				'>' => 'myTag',
				'@' => 'my Text'
			]));

			$this->assertSame("{$this->docStart}<myTag>my Text</myTag>", $builder->output());
		}

		public function testWrite_arraySingleElement_withAttributesAndContent() {

			$builder = new XmlBuilder();

			$builder->startDocument();


			$this->assertSame($builder, $builder->write([
				'>'  => 'myTag',
				'@b' => '15',
				'@c' => 'b',
				':b' => 'http://asd.de/xml',
				'@'  => 'my Text'
			]));

			$this->assertSame("{$this->docStart}<myTag b=\"15\" c=\"b\" xmlns:b=\"http://asd.de/xml\">my Text</myTag>", $builder->output());
		}

		public function testWrite_arrayMultipleElements() {
			$builder = new XmlBuilder();

			$builder->startDocument();
			$builder->startElement('root');


			$this->assertSame($builder, $builder->write([
				[
					'>' => 'myTag',
					'@' => 'my Text'
				],
				[
					'>' => 'myTag',
					'@' => 'another Text'
				],
				[
					'>' => 'anotherTag',
					'@' => 'my Text again'
				]
			]));

			$this->assertSame("{$this->docStart}<root><myTag>my Text</myTag><myTag>another Text</myTag><anotherTag>my Text again</anotherTag>", $builder->output());
		}

		public function testWrite_arrayWithContent_array() {
			$builder = new XmlBuilder();

			$builder->startDocument();

			$this->assertSame($builder, $builder->write([
				'>' => 'myTag',
				'@' => [
					[
						'>' => 'innerTag',
						'@' => 'another Text'
					],
					'anotherTag' => 'lastTag'
				]
			]));

			$this->assertSame("{$this->docStart}<myTag><innerTag>another Text</innerTag><anotherTag>lastTag</anotherTag></myTag>", $builder->output());
		}

		public function testWrite_tagHoldsKey_array() {
			$builder = new XmlBuilder();

			$builder->startDocument();

			$this->assertSame($builder, $builder->write([
				'myTag' => [
					[
						'>' => 'innerTag',
						'@' => 'another Text'
					],
					'anotherTag' => 'lastTag'
				]
			]));

			$this->assertSame("{$this->docStart}<myTag><innerTag>another Text</innerTag><anotherTag>lastTag</anotherTag></myTag>", $builder->output());
		}

		public function testWrite_tagHoldsKey_valueHoldsAttributes_array() {
			$builder = new XmlBuilder();

			$builder->startDocument();

			$this->assertSame($builder, $builder->write([
				'<myTag' => [
					'@attr1' => 15,
					'@attr2' => 'x',
					'@' => [
						[
							'>' => 'innerTag',
							'@' => 'another Text'
						],
						'anotherTag' => 'lastTag'
					]

				]
			]));

			$this->assertSame("{$this->docStart}<myTag attr1=\"15\" attr2=\"x\"><innerTag>another Text</innerTag><anotherTag>lastTag</anotherTag></myTag>", $builder->output());
		}

		public function testWrite_arrayWithContent_closure() {
			$builder = new XmlBuilder();

			$builder->startDocument();

			$this->assertSame($builder, $builder->write([
				'>' => 'myTag',
				'@' => function(XmlBuilder $wrt) {
					$wrt->writeComment('child comment');
				}
			]));

			$this->assertSame("{$this->docStart}<myTag><!--child comment--></myTag>", $builder->output());
		}

		public function testWrite_arrayWithContent_xmlSerializable() {
			$builder = new XmlBuilder();

			$builder->startDocument();

			$ser = $this->getMockBuilder(XmlSerializable::class)->getMock();
			$ser->method('xmlSerialize')
				->with($builder)
				->willReturnCallback(function ($b) {
					$b->writeComment('anotherComment');
				});


			$this->assertSame($builder, $builder->write([
				'>' => 'myTag',
				'@' => $ser
			]));

			$this->assertSame("{$this->docStart}<myTag><!--anotherComment--></myTag>", $builder->output());
		}

		public function testWrite_closure() {
			$builder = new XmlBuilder();

			$builder->startDocument();


			$this->assertSame($builder, $builder->write(function(XmlBuilder $wrt) use ($builder) {

				$this->assertSame($builder, $wrt);

				$wrt->writeComment('myComment');

			}));

			$this->assertSame("{$this->docStart}<!--myComment-->", $builder->output());
		}

		public function testWrite_xmlSerializable() {
			$builder = new XmlBuilder();

			$builder->startDocument();

			$ser = $this->getMockBuilder(XmlSerializable::class)->getMock();
			$ser->method('xmlSerialize')
				->with($builder)
				->willReturnCallback(function($b) {
					$b->writeComment('anotherComment');
				});


			$this->assertSame($builder, $builder->write($ser));

			$this->assertSame("{$this->docStart}<!--anotherComment-->", $builder->output());
		}

		public function testWrite_handler_date() {
			$builder = new XmlBuilder();

			$dt = new \DateTime();

			$this->assertSame($builder, $builder->setHandler(\DateTime::class, function(\DateTime $date, XmlBuilder $wrt) use ($dt, $builder) {
				$this->assertSame($dt, $date);
				$this->assertSame($builder, $wrt);

				$wrt->writeComment('Date handled');
			}));

			$builder->startDocument();

			$this->assertSame($builder, $builder->write($dt));

			$this->assertSame("{$this->docStart}<!--Date handled-->", $builder->output());
		}

		public function testWrite_handler_integer() {
			$builder = new XmlBuilder();

			$i = 45;

			$this->assertSame($builder, $builder->setHandler('integer', function($int, XmlBuilder $wrt) use ($i, $builder) {
				$this->assertSame($i, $int);
				$this->assertSame($builder, $wrt);

				$wrt->writeComment('Integer handled');
			}));

			$builder->startDocument();

			$this->assertSame($builder, $builder->write($i));

			$this->assertSame("{$this->docStart}<!--Integer handled-->", $builder->output());
		}

		public function testWrite_handler_string() {
			$builder = new XmlBuilder();

			$i = 'str';

			$this->assertSame($builder, $builder->setHandler('string', function($int, XmlBuilder $wrt) use ($i, $builder) {
				$this->assertSame($i, $int);
				$this->assertSame($builder, $wrt);

				$wrt->writeComment('String handled');
			}));

			$builder->startDocument();

			$this->assertSame($builder, $builder->write($i));

			$this->assertSame("{$this->docStart}<!--String handled-->", $builder->output());
		}

		public function testWrite_handler_float() {
			$builder = new XmlBuilder();

			$i = 45.6;

			$this->assertSame($builder, $builder->setHandler('double', function($int, XmlBuilder $wrt) use ($i, $builder) {
				$this->assertSame($i, $int);
				$this->assertSame($builder, $wrt);

				$wrt->writeComment('Double handled');
			}));

			$builder->startDocument();

			$this->assertSame($builder, $builder->write($i));

			$this->assertSame("{$this->docStart}<!--Double handled-->", $builder->output());
		}


		public function testWrite_handler_boolean() {
			$builder = new XmlBuilder();

			$i = true;

			$this->assertSame($builder, $builder->setHandler('boolean', function($int, XmlBuilder $wrt) use ($i, $builder) {
				$this->assertSame($i, $int);
				$this->assertSame($builder, $wrt);

				$wrt->writeComment('Boolean handled');
			}));

			$builder->startDocument();

			$this->assertSame($builder, $builder->write($i));

			$this->assertSame("{$this->docStart}<!--Boolean handled-->", $builder->output());
		}

		public function testWrite_null() {
			$builder = new XmlBuilder();

			$builder->startDocument();

			$this->assertSame($builder, $builder->write(null));

			$this->assertSame("{$this->docStart}", $builder->output());
		}

		public function testEncodeUtf8ToUtf8() {

			if (mb_internal_encoding() !== 'UTF-8')
				$this->markTestSkipped('Test requires UTF-8 as internal encoding');

			$builder = new XmlBuilder();

			$builder->startDocument();
			$builder->startElement('rootä');
			$builder->writeAttribute('ö', 'ü');

			$builder->text('äöü');

			$this->assertSame("{$this->docStart}<rootä ö=\"ü\">äöü", $builder->output());
		}

		public function testEncodeIsoToUtf8() {

			if (mb_internal_encoding() !== 'UTF-8')
				$this->markTestSkipped('Test requires UTF-8 as internal encoding');

			$builder = new XmlBuilder();
			$this->assertSame($builder, $builder->setInputEncoding('ISO-8859-1'));

			$builder->startDocument();
			$builder->startElement(utf8_decode('rootä'));
			$builder->writeAttribute(utf8_decode('ö'), utf8_decode('ü'));

			$builder->text(utf8_decode('äöü'));

			$this->assertSame("{$this->docStart}<rootä ö=\"ü\">äöü", $builder->output());
		}

		public function testEncodeUtf8ToIso() {

			if (mb_internal_encoding() !== 'UTF-8')
				$this->markTestSkipped('Test requires UTF-8 as internal encoding');

			$builder = new XmlBuilder();

			$builder->startDocument('1.0', 'ISO-8859-1');

			$builder->startElement('rootä');
			$builder->writeAttribute('ö', 'ü');
			$builder->text('äöü');

			$this->assertSame(utf8_decode("<?xml version=\"1.0\" encoding=\"ISO-8859-1\"?>\n<rootä ö=\"ü\">äöü"), $builder->flush());

		}



		public function testFlush_memory() {

			$builder = new XmlBuilder();

			$builder->startDocument();

			$builder->writeElement('my-tag');

			$this->assertSame("{$this->docStart}<my-tag/>", $builder->flush());
			$this->assertSame('', $builder->flush());


		}

		public function testFlush_resource() {

			$fd = fopen('php://temp', 'w+');



			$builder = new XmlBuilder($fd);

			$builder->startDocument();

			$builder->writeElement('my-tag');


			$this->assertSame(48, $builder->flush());
			$this->assertSame(0, $builder->flush());

			rewind($fd);
			$this->assertSame("{$this->docStart}<my-tag/>", stream_get_contents($fd));

		}

		public function testFlush_file() {
			$file = tempnam(sys_get_temp_dir(), 'xml-builder-test');

			$builder = new XmlBuilder($file);

			$builder->startDocument();

			$builder->writeElement('my-tag');

			$this->assertSame(48, $builder->flush());
			$this->assertSame(0, $builder->flush());

			$this->assertSame("{$this->docStart}<my-tag/>", file_get_contents($file));

		}

		public function testFlush_doNotEmpty() {

			$builder = new XmlBuilder();

			$builder->startDocument();

			$builder->writeElement('my-tag');

			$this->assertSame("{$this->docStart}<my-tag/>", $builder->flush(false));
			$this->assertSame("{$this->docStart}<my-tag/>", $builder->flush(false));


		}

		public function testIndent() {
			$builder = new XmlBuilder();

			$builder->startDocument();
			$this->assertSame($builder, $builder->setIndent(true, '  '));

			$builder->writeElement('my-tag', function(XmlBuilder $builder) {
				$builder->writeElement('inner', [ 'leaf' => 'content']);
			});

			$this->assertSame("{$this->docStart}<my-tag>\n  <inner>\n    <leaf>content</leaf>\n  </inner>\n</my-tag>\n", $builder->output());
		}

		public function testEach() {
			$builder = new XmlBuilder();

			$builder->startDocument();
			$builder->startElement('root');

			$this->assertSame($builder, $builder->each(['a','b','c'], function(XmlBuilder $builder, $v, $k) {
				$builder->writeElement($v, $k);
			}));


			$this->assertSame("{$this->docStart}<root><a>0</a><b>1</b><c>2</c>", $builder->output());

		}

		public function testMap() {
			$builder = new XmlBuilder();

			$builder->startDocument();
			$builder->startElement('root');

			$this->assertSame($builder, $builder->map(['a','b','c'], function($v, $k) {
				return $v . $k;
			}));


			$this->assertSame("{$this->docStart}<root>a0b1c2", $builder->output());

		}

		public function testWhen_true() {
			$builder = new XmlBuilder();

			$builder->startDocument();
			$builder->startElement('root');

			$this->assertSame($builder, $builder->when(true, function(XmlBuilder $builder) {
				$builder->writeElement('child');
			}));


			$this->assertSame("{$this->docStart}<root><child/>", $builder->output());

		}

		public function testWhen_false() {
			$builder = new XmlBuilder();

			$builder->startDocument();
			$builder->startElement('root');

			$this->assertSame($builder, $builder->when(false, function(XmlBuilder $builder) {
				$builder->writeElement('child');
			}));


			$this->assertSame("{$this->docStart}<root", $builder->output());

		}

		public function testWhen_closure_true() {
			$builder = new XmlBuilder();

			$builder->startDocument();
			$builder->startElement('root');

			$this->assertSame($builder, $builder->when(function() { return true; }, function (XmlBuilder $builder) {
				$builder->writeElement('child');
			}));


			$this->assertSame("{$this->docStart}<root><child/>", $builder->output());

		}

		public function testWhen_closure_false() {
			$builder = new XmlBuilder();

			$builder->startDocument();
			$builder->startElement('root');

			$this->assertSame($builder, $builder->when(function() { return false; }, function (XmlBuilder $builder) {
				$builder->writeElement('child');
			}));


			$this->assertSame("{$this->docStart}<root", $builder->output());

		}

		public function testTernary_truthy_closures() {
			$builder = new XmlBuilder();

			$builder->startDocument();
			$builder->startElement('root');

			$exp = 'name';

			$this->assertSame($builder, $builder->ternary(
				$exp,
				function ($v) {
					return 'then:' . $v;
				},
				function ($v) {
					return 'else:' . $v;
				}
			));


			$this->assertSame("{$this->docStart}<root>then:name", $builder->output());

		}

		public function testTernary_falsy_closures() {
			$builder = new XmlBuilder();

			$builder->startDocument();
			$builder->startElement('root');

			$exp = 0;

			$this->assertSame($builder, $builder->ternary(
				$exp,
				function ($v) {
					return 'then:' . $v;
				},
				function ($v) {
					return 'else:' . $v;
				}
			));


			$this->assertSame("{$this->docStart}<root>else:0", $builder->output());

		}

		public function testTernary_truthy_values() {
			$builder = new XmlBuilder();

			$builder->startDocument();
			$builder->startElement('root');

			$exp = 'name';

			$this->assertSame($builder, $builder->ternary(
				$exp,
				'then',
				'else'
			));


			$this->assertSame("{$this->docStart}<root>then", $builder->output());

		}

		public function testTernary_falsy_values() {
			$builder = new XmlBuilder();

			$builder->startDocument();
			$builder->startElement('root');

			$exp = null;

			$this->assertSame($builder, $builder->ternary(
				$exp,
				'then',
				'else'
			));


			$this->assertSame("{$this->docStart}<root>else", $builder->output());

		}

		public function testTernary_closure_truthy_closures() {
			$builder = new XmlBuilder();

			$builder->startDocument();
			$builder->startElement('root');

			$this->assertSame($builder, $builder->ternary(
				function() {
					return 'name';
				},
				function ($v) {
					return 'then:' . $v;
				},
				function ($v) {
					return 'else:' . $v;
				}
			));


			$this->assertSame("{$this->docStart}<root>then:name", $builder->output());

		}

		public function testTernary_closure_falsy_closures() {
			$builder = new XmlBuilder();

			$builder->startDocument();
			$builder->startElement('root');

			$this->assertSame($builder, $builder->ternary(
				function () {
					return 0;
				},
				function ($v) {
					return 'then:' . $v;
				},
				function ($v) {
					return 'else:' . $v;
				}
			));


			$this->assertSame("{$this->docStart}<root>else:0", $builder->output());

		}

		public function testTernary_closure_truthy_values() {
			$builder = new XmlBuilder();

			$builder->startDocument();
			$builder->startElement('root');

			$this->assertSame($builder, $builder->ternary(
				function () {
					return 'name';
				},
				'then',
				'else'
			));


			$this->assertSame("{$this->docStart}<root>then", $builder->output());

		}

		public function testTernary_closure_falsy_values() {
			$builder = new XmlBuilder();

			$builder->startDocument();
			$builder->startElement('root');

			$this->assertSame($builder, $builder->ternary(
				function () {
					return null;
				},
				'then',
				'else'
			));


			$this->assertSame("{$this->docStart}<root>else", $builder->output());

		}

		public function testAutoFlush() {

			$fd = fopen('php://memory', 'w+');

			$builder = new XmlBuilder($fd);

			$this->assertSame($builder, $builder->autoFlush(100));

			$builder->startDocument();
			$builder->startElement('root');
			$builder->text(str_repeat('1', 50));

			// yet nothing should be flushed
			rewind($fd);
			$this->assertEquals('', stream_get_contents($fd));

			$builder->text(str_repeat('1', 50));

			// now it should be flushed
			rewind($fd);
			$this->assertEquals("{$this->docStart}<root>" . str_repeat('1', 100), stream_get_contents($fd));

			$builder->text(str_repeat('1', 50));

			// nothing else should be flushed
			rewind($fd);
			$this->assertEquals("{$this->docStart}<root>" . str_repeat('1', 100), stream_get_contents($fd));

			$builder->endElement();
			$builder->endDocument();

			// now, everything should be flushed
			rewind($fd);
			$this->assertEquals("{$this->docStart}<root>" . str_repeat('1', 150) . "</root>\n", stream_get_contents($fd));
		}

		public function testAutoFlush_memory() {

			$builder = new XmlBuilder();

			$this->expectException(\RuntimeException::class);

			$builder->autoFlush();
		}

		public function testResourceIsKeptOpenAfterDestruct() {

			$res = fopen('php://memory', 'w+');

			$cb = function () use ($res) {
				$builder = new XmlBuilder($res);

				$builder->startDocument();
				$builder->writeElement('root');
				$builder->endDocument();
			};

			$cb();

			$this->assertEquals('resource', gettype($res));

		}

		public function testNoErrorOnDestructWhenStreamAlreadyClosed() {

			$res = fopen('php://memory', 'w+');

			$cb = function () use ($res) {
				$builder = new XmlBuilder($res);

				$builder->startDocument();
				$builder->writeElement('root');
				$builder->endDocument();

				fclose($res);
			};

			$cb();

			$this->expectNotToPerformAssertions();


		}

		public function testWriteInvalidElementName() {
			$builder = new XmlBuilder();

			$builder->startDocument();

			$this->expectException(XmlException::class);
			$this->expectExceptionMessage('Invalid Element Name');

			$builder->startElement('<root');
		}

	}