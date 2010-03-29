<?php
require_once("lime.php");
require_once(dirname(__FILE__) . "/../Nekorm.php");

$t = new lime_test;

$t->ok( StrictNameRule::isSafe("a0_table"), "normal" );
$t->ok( StrictNameRule::isSafe("A0_TABLE"), "uc" );
$t->is( false, StrictNameRule::isSafe("a0-table"), "hyphen" );
$t->is( false, StrictNameRule::isSafe("Ａ０＿ＴＡＢＬＥ"), "multi byte" );
$t->is( false, StrictNameRule::isSafe("日本語"), "multi byte" );
$t->is( false, StrictNameRule::isSafe("a0\x00 1=1 OR"), "null byte" );
$t->is( false, StrictNameRule::isSafe("a0\n 1=1 OR"), "return" );
$t->is( false, StrictNameRule::isSafe("a0; 1=1 OR"), "semi colon" );
$t->is( false, StrictNameRule::isSafe("a0' 1=1 OR"), "quote" );

$t->is( "a0_table", StrictNameRule::sFromUsName("a0_table"), "normal" );
$t->is( "A0_TABLE", StrictNameRule::sFromUsName("A0_TABLE"), "normal" );
$t->is( null, StrictNameRule::sFromUsName("a0-table"), "hyphen" );
$t->is( null, StrictNameRule::sFromUsName("Ａ０＿ＴＡＢＬＥ"), "multi byte" );
$t->is( null, StrictNameRule::sFromUsName("日本語"), "multi byte" );
$t->is( null, StrictNameRule::sFromUsName("a0\x00 1=1 OR"), "null byte" );
$t->is( null, StrictNameRule::sFromUsName("a0\n 1=1 OR"), "return" );
$t->is( null, StrictNameRule::sFromUsName("a0; 1=1 OR"), "semi colon" );
$t->is( null, StrictNameRule::sFromUsName("a0' 1=1 OR"), "quote" );

$t->is_deeply(
	array("name", "created_on"),
	StrictNameRule::sFromUsColumnArray(array("name", "created_on")),
	"normal"
);
$t->is(
	null,
	StrictNameRule::sFromUsColumnArray(array("name", "created-on")),
	"hyphen"
);
$t->is(
	null,
	StrictNameRule::sFromUsColumnArray(array("名前", "created_on")),
	"multi byte"
);
$t->is(
	null,
	StrictNameRule::sFromUsColumnArray(array("name\x00 1=1 OR", "created_on")),
	"null byte"
);
$t->is(
	null,
	StrictNameRule::sFromUsColumnArray(array("name; 1=1 OR", "created_on")),
	"semi colon"
);
$t->is(
	null,
	StrictNameRule::sFromUsColumnArray(array("name' 1=1 OR", "created_on")),
	"quote"
);
$t->is(
	null,
	StrictNameRule::sFromUsColumnArray(array("name\n 1=1 OR", "created_on")),
	"return"
);

$t->is(
	NekoSchema::getInstance(
		"table-name",
		"id",
		array("name", "age", "created_on")
	),
	null,
	"hyphen in table name");
$t->is(
	NekoSchema::getInstance(
		"table_name",
		"user-id",
		array("name", "age", "created_on")
	),
	null,
	"hyphen in pk");
$t->is(
	NekoSchema::getInstance(
		"table_name",
		"id",
		array("user-name", "age", "created_on")
	),
	null,
	"hyphen in column name");
$t->is(
	NekoSchema::getInstance(
		"table_name\x00null byte",
		"id",
		array("name", "age", "created_on")
	),
	null,
	"null byte");

$schema = NekoSchema::getInstance(
	"user_master",
	"id",
	array("name", "age", "created_on"));
$t->ok( $schema instanceof NekoSchema, "instance");

$t->is( $schema->sPkey(), "id", "pk");
$t->is( $schema->sTable(), "user_master", "sTable");
$t->is( implode(",", $schema->sColumns()), "name,age,created_on", "columns");

$t->ok( $schema->isValidColumn("id"), "isValidColumn");
$t->ok( $schema->isValidColumn("name"), "isValidColumn");
$t->ok( $schema->isValidColumn("age"), "isValidColumn");
$t->is( false, $schema->isValidColumn("hoge"), "invalid column");
$t->is( false, $schema->isValidColumn("id\x00hoge"), "invalid columns(null byte)");
$t->is( false, $schema->isValidColumn("名前"), "invalid column");

$t->is_deeply(
	$schema->sFromUsField(array(
		"name"=>"s-tanno",
	   	"age"=>1000)),
	array("name"=>"s-tanno", "age"=>1000),
	"sFromUsField");
$t->is_deeply(
	$schema->sFromUsField(array(
		"hoge"=>"fuga",
	   	"name"=>"s-tanno",
	   	"age"=>1000)),
	array("name"=>"s-tanno", "age"=>1000),
	"sFromUsField filter");

$t->is_deeply(
	$schema->retrieveQuery(1),
	array(
		"sQuery" => "SELECT * FROM user_master WHERE id = ? ",
		"usData" => array(1)),
	"retrieve");
$t->is_deeply(
	$schema->deleteQuery(1),
	array(
		"sQuery" => "DELETE FROM user_master WHERE id = ? ",
		"usData" => array(1)),
	"delete");
$t->is_deeply(
	$schema->insertQuery(array(
		"name"=>"s-tanno",
		"age"=>1000
	)),
	array(
		"sQuery"=>"INSERT INTO user_master (age, name) values (?, ?)",
		"usData"=>array(1000, "s-tanno")),
	"insert");
$t->is_deeply(
	$schema->insertQuery(array(
		"name"=>"s-tanno",
		"age"=>1000,
		"title"=>"the God"
	)),
	array(
		"sQuery"=>"INSERT INTO user_master (age, name) values (?, ?)",
		"usData"=>array(1000, "s-tanno")),
	"insert(余分を削除)");
$t->is_deeply(
	$schema->updateQuery(3, array(
		"name"=>"s-tanno",
		"age"=>1000
	)),
	array(
		"sQuery"=>"UPDATE user_master SET age = ?, SET name = ? WHERE id = ?",
		"usData"=>array(1000, "s-tanno", 3)),
	"update");
$t->is_deeply(
	$schema->updateQuery(3, array(
		"name"=>"s-tanno",
		"age"=>1000,
		"title"=>"the God"
	)),
	array(
		"sQuery"=>"UPDATE user_master SET age = ?, SET name = ? WHERE id = ?",
		"usData"=>array(1000, "s-tanno", 3)),
	"update(余分を削除)");

$t->is_deeply(
	$schema->selectQuery(array(
		"name"=>"s-tanno",
		"age"=>1000
	)),
	array(
		"sQuery"=>"SELECT * FROM user_master WHERE age = ? AND name = ?",
		"usData"=>array(1000, "s-tanno")),
	"select(余分を削除)");

$t->is_deeply(
	$schema->selectQuery(array(
		"name"=>"s-tanno",
		"age"=>1000,
		"OR 1=1 "=>"the God"
	)),
	array(
		"sQuery"=>"SELECT * FROM user_master WHERE age = ? AND name = ?",
		"usData"=>array(1000, "s-tanno")),
	"select(余分を削除)");

