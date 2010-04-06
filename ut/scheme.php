<?php
require_once("lime.php");
require_once(dirname(__FILE__) . "/../Nekorm.php");

$t = new lime_test;

testSchemaGetInstance($t);
testStrictNameRule($t);
testAccessor($t);
testIsValidColumn($t);
testSFromUsColsList($t);
testRetrieveQuery($t);
testInsertQuery($t);
testDeleteQuery($t);
testUpdateQuery($t);
testSelectQuery($t);
testDeleteWhereQuery($t);
exit;

function testSchemaGetInstance($t){
	$t->is(
		NekoSchema::getInstance("table-name",
			"id",
			array("name", "age", "created_on")
		),
		null,
		"hyphen in table name");
	$t->is(
		NekoSchema::getInstance("table_name",
			"user-id",
			array("name", "age", "created_on")
		),
		null,
		"hyphen in pk");
	$t->is(
		NekoSchema::getInstance("table_name",
			"id",
			array("user-name", "age", "created_on")
		),
		null,
		"hyphen in column name");
	$t->is(
		NekoSchema::getInstance("table_name\x00null byte",
			"id",
			array("name", "age", "created_on")
		),
		null,
		"null byte");
}
function testStrictNameRule($t){
	$isSafeTestCase = array(
		array(true,  'a0_table',         'normal'),
		array(true,  'A0_TABLE',         'upper case'),
		array(false, 'a0-table',         'hyphen'),
		array(false, 'Ａ０＿ＴＡＢＬＥ', 'multi bytes'),
		array(false, '日本語',           'multi bytes'),
		array(false, "a0\x00 OR 1=1 ",   'null byte'),
		array(false, "a0\n OR 1=1 ",     'return'),
		array(false, "a0; OR 1=1",       'semi coron'),
		array(false, "a0' OR ''='",      'quote'),
	);
	foreach( $isSafeTestCase as $case ) {
		$expect = $case[0]; $value = $case[1]; $explain = $case[2];
		$t->ok( $expect===StrictNameRule::isSafe($value), $explain );
	}

	$sFromUsNameTestCase = array(
		array('a0_table', 'a0_table',         'normal'),
		array('A0_TABLE', 'A0_TABLE',         'upper case'),
		array(null,       'a0-table',         'hyphen'),
		array(null,       'Ａ０＿ＴＡＢＬＥ', 'multi bytes'),
		array(null,       '日本語',           'multi bytes'),
		array(null,       "a0\x00 OR 1=1 ",   'null byte'),
		array(null,       "a0\n OR 1=1 ",     'return'),
		array(null,       "a0; OR 1=1",       'semi coron'),
		array(null,       "a0' OR ''='",      'quote'),
	);
	foreach( $sFromUsNameTestCase as $case ) {
		$expect = $case[0]; $value = $case[1]; $explain = $case[2];
		$t->ok( $expect===StrictNameRule::sFromUsName($value), $explain );
	}

	$sFromUsNameArrayTestCase = array(
		array(null, array("name", "created-on"), "hyphen"),
		array(null, array("名前", "created_on"), "multi byte"),
		array(null, array("name\x00 1=1 OR"),    "null byte"),
		array(null, array("name, 1=1 OR"),       "semi colon"),
		array(null, array("name' 1=1 OR"),       "quote"),
		array(null, array("name\n 1=1 OR"),      "return"),
		array(array('name','created_on'), array('name','created_on'), 'normal'),
	);
	foreach( $sFromUsNameArrayTestCase as $case ) {
		$expect = $case[0]; $value = $case[1]; $explain = $case[2];
		$t->is( $expect, StrictNameRule::sFromUsColumnArray($value), $explain );
	}
}
function testAccessor($t){
	$schema = NekoSchema::getInstance(
		"user_master",
		"id",
		array("name", "age", "created_on"));
	$t->ok( $schema instanceof NekoSchema, "instance");
	$t->is( $schema->sPkey(), "id", "pk");
	$t->is( $schema->sTable(), "user_master", "sTable");
	$t->is( implode(",", $schema->sColumns()), "name,age,created_on", "columns");
}
function testIsValidColumn($t){
	$schema = NekoSchema::getInstance(
		"user_master",
		"id",
		array("name", "age", "created_on"));

	$isValidColumnTestCase = array(
		array(true,  'id', 'normal'),
		array(true,  'age', 'normal'),
		array(true,  'name', 'normal'),
		array(false, 'hoge', 'not found'),
		array(false, "id\x00hoge", 'null byte'),
		array(false, '日本語', 'multibyte'),
	);
	foreach( $isValidColumnTestCase as $case ){
		$expect = $case[0]; $value = $case[1]; $explain = $case[2];
		$t->ok($expect === $schema->isValidColumn($value), $explain);
	}
}
function testSFromUsColsList($t){
	$schema = NekoSchema::getInstance(
		"user_master",
		"id",
		array("name", "age", "created_on"));
	$sFromUsColsListTestCase = array(
		array(array('age','name'), array('name', 'age'), 'normal'),
		array(array('age','name'), array('age', 'name'), 'order'),
		array(array('age','name'), array('age', 'name', 'hoge'), 'filter'),
	);
	foreach( $sFromUsColsListTestCase as $case ){
		$expect = $case[0]; $value = $case[1]; $explain = $case[2];
		$t->is($expect, $schema->sFromUsColsList($value), $explain);
	}
}
function testRetrieveQuery($t){
	$schema = NekoSchema::getInstance(
		"user_master",
		"id",
		array("name", "age", "created_on"));

	$t->is(
		$schema->retrieveQuery(1),
		array(
			"sQuery" => "SELECT * FROM user_master WHERE id = ? ",
			"usData" => array(1)),
		"retrieve");
}
function testInsertQuery($t){
	$schema = NekoSchema::getInstance(
		"user_master",
		"id",
		array("name", "age", "created_on"));

	$insertQueryTestCase = array(
		array(null, array(), 'null'),
		array(null, array('hoge'), 'filter'),
		array(
			array(
				'sQuery'=>'INSERT INTO user_master (age, name) values (?, ?)',
				'usData'=>array(1000, 's-tanno')),
			array('name'=>'s-tanno', 'age'=>1000),
			'normal'),
		array(
			array(
				'sQuery'=>'INSERT INTO user_master (age, name) values (?, ?)',
				'usData'=>array(1000, 's-tanno')),
			array('title'=>'the GOD','name'=>'s-tanno', 'age'=>1000),
			'filter')
	);
	foreach ( $insertQueryTestCase as $case ) {
		$expect = $case[0]; $value = $case[1]; $explain = $case[2];
		$t->is($expect, $schema->insertQuery($value), $explain);
	}

	$schema->addOnInsertTimestamp('created_on');

	$insertQueryTestCase = array(
		array(
			array(
				'sQuery'=>'INSERT INTO user_master (age, name, created_on) values (?, ?, CURRENT_TIMESTAMP)',
				'usData'=>array(1000, 's-tanno')),
			array('name'=>'s-tanno', 'age'=>1000),
			'normal'),
		array(
			array(
				'sQuery'=>'INSERT INTO user_master (age, name, created_on) values (?, ?, CURRENT_TIMESTAMP)',
				'usData'=>array(1000, 's-tanno')),
			array('title'=>'the GOD','name'=>'s-tanno', 'age'=>1000),
			'filter'),
		array(
			array(
				'sQuery'=>'INSERT INTO user_master (created_on) values (CURRENT_TIMESTAMP)',
				'usData'=>array()),
			array(),
			'null'),
	);
	foreach ( $insertQueryTestCase as $case ) {
		$expect = $case[0]; $value = $case[1]; $explain = $case[2];
		$t->is($expect, $schema->insertQuery($value), $explain);
	}
}
function testDeleteQuery($t){
	$schema = NekoSchema::getInstance(
		"user_master",
		"id",
		array("name", "age", "created_on"));
	$t->is(
		$schema->deleteQuery(1),
		array(
			"sQuery" => "DELETE FROM user_master WHERE id = ? ",
			"usData" => array(1)),
		"normal");
	$t->is(
		$schema->deleteQuery(null),
		array(
			"sQuery" => "DELETE FROM user_master WHERE id = ? ",
			"usData" => array(null)),
		"delete");
}
function testUpdateQuery($t){
	$schema = NekoSchema::getInstance(
		"user_master",
		"id",
		array("name", "age", "created_on"));

	$t->is(
		$schema->updateQuery(3, array(
			"name" =>"s-tanno",
			"age"  =>1000
		)),
		array(
			"sQuery"=>"UPDATE user_master SET age = ?, name = ? WHERE id = ?",
			"usData"=>array(1000, "s-tanno", 3)),
		"update");
	$t->is(
		$schema->updateQuery(3, array(
			"name"  =>"s-tanno",
			"age"   =>1000,
			"title" =>"the God"
		)),
		array(
			"sQuery"=>"UPDATE user_master SET age = ?, name = ? WHERE id = ?",
			"usData"=>array(1000, "s-tanno", 3)),
		"update(余分を削除)");
	$t->is($schema->updateQuery(3, array()), null, 'update(null)');

	$schema->addOnInsertTimestamp("created_on");
	$schema->addOnUpdateTimestamp("created_on");
	$t->is(
		$schema->updateQuery(1, array(
			"name"  =>"s-tanno",
			"age"   =>1000,
			"title" =>"the God"
		)),
		array(
			"sQuery"=>"UPDATE user_master SET age = ?, name = ?, created_on = CURRENT_TIMESTAMP WHERE id = ?",
			"usData"=>array(1000, "s-tanno", 1)),
		"insert(余分を削除)");
}
function testSelectQuery($t){
	$schema = NekoSchema::getInstance(
		"user_master",
		"id",
		array("name", "age", "created_on"));

	$t->is($schema->selectQuery(array()), null, 'select(null)');
	$t->is(
		$schema->selectQuery(array("name"=>"s-tanno","age"=>1000)),
		array(
			"sQuery"=>"SELECT * FROM user_master WHERE age = ? AND name = ?",
			"usData"=>array(1000, "s-tanno")),
		"select(余分を削除)");

	$t->is(
		$schema->selectQuery(array(
			"name"    =>"s-tanno",
			"age"     =>1000,
			"OR 1=1 " =>"the God"
		)),
		array(
			"sQuery" =>"SELECT * FROM user_master WHERE age = ? AND name = ?",
			"usData" =>array(1000, "s-tanno")),
		"select(余分を削除)");
}
function testDeleteWhereQuery($t){
	$schema = NekoSchema::getInstance(
		"user_master",
		"id",
		array("name", "age", "created_on"));
	$t->is($schema->deleteWhereQuery(array()), null, 'delete(null)');
	$t->is(
		$schema->deleteWhereQuery(array(
			'name' =>"' OR ''='",
		   	'age'  =>1000)),
		array(
			'sQuery'=>'DELETE FROM user_master WHERE age = ? AND name = ?',
			'usData'=>array(1000, "' OR ''='")));
}
function testSelectAllQuery($t){
	$schema = NekoSchema::getInstance(
		"user_master",
		"id",
		array("name", "age", "created_on"));
	$t->is($schema->selectAllQuery(), 'SELECT * FROM user_master', 'select all');
}
