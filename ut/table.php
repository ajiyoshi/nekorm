<?php
require_once("lime.php");
require_once(dirname(__FILE__) . "/../Nekorm.php");

class DoneUrlTable extends NekoTable {
	function __construct($dbh){
		parent::__construct(
			$dbh,
			NekoSchema::getInstance(
				"done_url",
				"id",
				array("user_id", "url")));
	}
}

$t = new lime_test;
$dbh = setup();
testInstance($t, $dbh);
testInsertAndSelectAndDelete($t, $dbh);
testInsertAndUpdate($t, $dbh);
testUnique($t, $dbh);
testNotFound($t, $dbh);

function setup(){
	$forSqlite = false;
	if($forSqlite){
		$dbh = new PDO('sqlite::memory:');
		$dbh->query("
		create table done_url (
			id integer primary key,
			user_id integer unique,
			url varchar(255) not null
		)
		") or die($dbh->errorInfo());
		return $dbh;
	}else{
		$dbh = new PDO('mysql:host=localhost;dbname=test', 'test');
		$dbh->query("
		create table if not exists done_url (
			id integer primary key auto_increment,
			user_id integer unique,
			url varchar(255) not null
		)
		") or die($dbh->errorInfo());
		$dbh->query("delete from done_url");
		return $dbh;
	}
}
function testInstance($t, $dbh){
	$t->ok( new DoneUrlTable($dbh) instanceof NekoTable, "instance" );
	$t->ok( new DoneUrlTable($dbh) instanceof DoneUrlTable, "instance" );
}
function testInsertAndSelectAndDelete($t, $dbh){
	$done_url = new DoneUrlTable($dbh);
	//insert
	$obj = $done_url->insert(array(
		"user_id"	=> 1,
		"url"		=> "http://buzzurl.jp"));
	$t->ok( $obj instanceof NekoRow, "insert" );

	//select
	$other = $done_url->retrieve($obj->id);
	$t->is( $other->url, "http://buzzurl.jp", "select" );

	//cleanup
	$other->delete();
	$t->is( $done_url->retrieve($obj->id), null, "delete" );

}
function testInsertAndUpdate($t, $dbh){
	$done_url = new DoneUrlTable($dbh);
	//insert
	$obj = $done_url->insert(array(
		"user_id"	=> 1,
		"url"		=> "http://buzzurl.jp"));
	$t->ok( $obj instanceof NekoRow, "insert" );

	//update
	$obj->url = "http://yahoo.co.jp";
	$obj->update();

	//check
	$other = $done_url->retrieve($obj->id);
	$t->is( $other->url, "http://yahoo.co.jp", "update" );
	$t->is( $other->user_id, 1, "update" );

	//cleanup
	$other->delete();
	$t->is( $done_url->retrieve($obj->id), null, "delete" );
}
function testUnique($t, $dbh){
	$done_url = new DoneUrlTable($dbh);
	//insert
	$obj = $done_url->insert(array(
		"user_id"	=> 1,
		"url"		=> "http://buzzurl.jp"));
	$t->ok( $obj instanceof NekoRow, "insert" );

	//user_id is unique
	try {
		$obj = $done_url->insert(array(
			"user_id"	=> 1,
			"url"		=> "http://ecnavi.jp"
		));
		$t->fail("unique");
	}catch(Exception $e){
		$t->pass("unique");
	}
	$obj->delete();
}
function testNotFound($t, $dbh){
	$done_url = new DoneUrlTable($dbh);
	//search()による検索結果は配列であり、該当がない場合は空集合(array())が返る。
	$t->is_deeply(
		$done_url->search(array( "user_id" => 100 )),
		array(),
		"empty"
	);
	//retrieve()による検索結果はオブジェクトであり、該当がない場合はnullが返る。
	$t->ok( $done_url->retrieve(-1) === null, "empty" );
}
