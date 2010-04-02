<?php
require_once("lime.php");
require_once(dirname(__FILE__) . "/../Nekorm.php");

class BookmarkTable extends NekoTable {
	function __construct($dbh){
		$schema = NekoSchema::getInstance(
			"bookmarks",
			"id",
			array(
				"user_id",
				"url",
				'comment',
				"created_on",
			   	"updated_on"));
		$schema->addOnInsertTimestamp("created_on");
		$schema->addOnInsertTimestamp("updated_on");
		$schema->addOnUpdateTimestamp("updated_on");
		parent::__construct( $dbh, $schema );
	}
}

$t = new lime_test;
$dbh = setup();
testRollBack($t, $dbh);

function setup(){
	$dbh = new PDO('sqlite::memory:');
	$dbh->query("
		create table bookmarks (
			id integer primary key,
			user_id integer not null,
			url varchar(255) not null,
			created_on datetime not null,
			updated_on datetime not null
		)
		") or die($dbh->errorInfo());
	return $dbh;
}
function testRollBack($t, $dbh){
	$table = new BookmarkTable($dbh);
	try {
		$table->insert(array('user_id'=>1,'url'=>'http://buzzurl.jp/1'));
		$table->insert(array('user_id'=>1,'url'=>'http://buzzurl.jp/2'));
		$table->insert(array('user_id'=>1,'url'=>'http://buzzurl.jp/3'));
		$t->pass();
	} catch(Exception $e) {
		$t->fail();
	}
	try {
		$table->beginTransaction();
		$table->delete_where(array('user_id'=>1));
		$t->ok(0===count($table->search(array('user_id'=>1))));
		$table->rollBack();
		$t->pass();
	} catch(Exception $e) {
		$t->fail();
	}
	$ls = $table->search(array('user_id'=>1));
	$t->ok(3===count($ls));
	$t->ok('http://buzzurl.jp/1'===$ls[0]->url);
	$t->ok('http://buzzurl.jp/2'===$ls[1]->url);
	$t->ok('http://buzzurl.jp/3'===$ls[2]->url);

	try {
		$table->delete_where(array('user_id'=>1));
		$t->ok( 0===count($table->search(array('user_id'=>1))) );
	} catch(Exception $e) {
		$t->fail();
	}
}
