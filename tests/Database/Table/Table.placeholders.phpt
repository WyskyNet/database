<?php

/**
 * Test: Nette\Database\Table\SqlBuilder: Escaping with SqlLiteral.
 * @dataProvider? ../databases.ini
 */

use Nette\Database\SqlLiteral;
use Tester\Assert;

require __DIR__ . '/../connect.inc.php'; // create $connection

Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/../files/{$driverName}-nette_test1.sql");


test(function () use ($context, $driverName) {
	// Leave literals lower-cased, also not-delimiting them is tested.
	switch ($driverName) {
		case 'mysql':
			$literal = new SqlLiteral('year(now())');
			break;
		case 'pgsql':
			$literal = new SqlLiteral('extract(year from now())::int');
			break;
		case 'sqlite':
			$literal = new SqlLiteral("cast(strftime('%Y', date('now')) as integer)");
			break;
		case 'sqlsrv':
			$literal = new SqlLiteral('year(cast(current_timestamp as datetime))');
			break;
		default:
			Assert::fail("Unsupported driver $driverName");
	}

	$selection = $context
		->table('book')
		->select('? AS col1', 'hi there!')
		->select('? AS col2', $literal);

	$row = $selection->fetch();
	Assert::same('hi there!', $row['col1']);
	Assert::same((int) date('Y'), $row['col2']);
});


test(function () use ($context) {
	$bookTagsCount = [];
	$books = $context
		->table('book')
		->select('book.title, COUNT(DISTINCT :book_tag.tag_id) AS tagsCount')
		->group('book.title')
		->having('COUNT(DISTINCT :book_tag.tag_id) < ?', 2)
		->order('book.title');

	foreach ($books as $book) {
		$bookTagsCount[$book->title] = $book->tagsCount;
	}

	Assert::same([
		'JUSH' => 1,
		'Nette' => 1,
	], $bookTagsCount);
});


test(function () use ($context, $driverName) {
	if ($driverName === 'mysql') {
		$authors = [];
		$selection = $context->table('author')->order('FIELD(name, ?)', ['Jakub Vrana', 'David Grudl', 'Geek']);
		foreach ($selection as $author) {
			$authors[] = $author->name;
		}

		Assert::same(['Jakub Vrana', 'David Grudl', 'Geek'], $authors);
	}
});


test(function () use ($context, $driverName) { // Test placeholder for GroupedSelection
	if ($driverName === 'sqlsrv') { // This syntax is not supported on SQL Server
		return;
	}

	$books = $context->table('author')->get(11)->related('book')->order('title = ? DESC', 'Test');
	foreach ($books as $book) {
	}

	$books = $context->table('author')->get(11)->related('book')->select('SUBSTR(title, ?)', 3);
	foreach ($books as $book) {
	}
});
