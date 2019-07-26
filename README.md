# Easy-XML

## Writing XML files
The `XMLBuilder` class offers a similar API as the PHP's `XMLWriter`.
In fact it utilizes it internally. But most of the time this way
of generating XML is not very intuitive and your source code does
not reveal the XML structure clearly.

Therefore the `XMLBuilder` offers a powerful array based 
interface which reveals the XML structure in your PHP code.
It's always a good idea if your source code reveals it's purpose
at first glance.

### Getting started

The following example demonstrates how to create a basic XML with
nested nodes:

	$builder = new XMLBuilder();
	$builder
		->beginDocument()
		->write([
			'root' => [
				'child'        => 'content',
				'anotherChild' => 15
		]);
	
The tag name is represented by the array key. The content is
determined by the array value:

	<?xml version="1.0" encoding="UTF-8"?>
	<root>
		<child>content</child>
		<anotherChild>15</anotherChild>
	</root>		
	
	
#### Adding attributes

To add attributes to our XML nodes, we prefix the key with
an open XML bracket ("<") and pass an array with attribute names 
as keys prefixed with "@". The content is defined by the item
simply named "@". Or you use an numeric array and specify the
the tag name inside using the ">" key:

	$builder->write([
		'<root' => [
			'@author' => 'Paul'
			'@age'    => 40,
			'@'       => [
				'<child' => [
					'@age' => '4'
					'@'    => 'Lukas'
				],
				
				// alternative syntax:
				
				[
					'>'    => 'wife',
					'@age' => 36,
					'@'    => 'Lisa'
				]
			]
		]
	]);
	
As you see, you even can mix the different methods of defining
XML nodes. The resulting XML would look like this:

	<?xml version="1.0" encoding="UTF-8"?>
	<root author="Paul" age="40">
		<child age="4">content</child>
		<wife age="36">Lisa</wife>
	</root>
	
#### Multiple nodes with same name

Sometimes you need to define multiple siblings with the same name.
There are two ways to do so. Either you wrap each node in an array
or you use the ">" key to define the tag name:

	$builder->write([
		'root' => [
			['child' => 'Lukas'],
			['child' => 'Sophie'],
			
			// alternative syntax:
			
			[
				'>' => 'child',
				'@' => 'Michael'
			],
			[
				'>' => 'child',
				'@' => 'Zoe'
			]
		]
	]);
	
#### Passing closures
Often the XML structure depends on the data to write. To keep
a fluent interface and a visual structure of the XML in your
source, you may pass a Closure to generate dynamic parts of
your XML:

	$builder->write([
		'root' => function(XMLBuilder $bld) use ($person) {
			if ($person instanceof Child)
				$bld->write(['child' => $person->getName()];
			else
				$bld->write(['adult' => $person->getName()];
		});
	
	

