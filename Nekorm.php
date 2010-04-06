<?php
/*XXX
 * このルールではテーブル名やカラム名として[_a-zA-Z0-9]のみ使用可能。
 * " select 社員名 from 社員マスタ where 社員ID = ? "
 * とかやりたい人は自分でそのための
 * (安全な)スキーマバリデーションクラスを作ること
 */
class StrictNameRule {
	public static function isSafe($usName){
		if( preg_match("/[^_a-z0-9]/i", $usName) ){
			return false;
		}else{
			return true;
		}
	}
	public static function sFromUsName($usName){
		$sName = null;
		if( StrictNameRule::isSafe($usName) ){
			$sName = $usName;
		}
		return $sName;
	}
	public static function sFromUsColumnArray($usColumns){
		foreach( $usColumns as $usCol ){
			if( !StrictNameRule::isSafe($usCol) ){
				return null;
			}
		}
		return $usColumns;
	}
}

/*
 * + (NekoSchema)getInstance(string $table, string $pk, array $columns)
 * - (bool)isValidColumn(string $usColumn)
 * - (array)sFromUsColsList(array $usColumnArray)
 * - (array)sColumns()
 * - (string)sTable()
 * - (string)sPkey()
 * - (Query)retrieveQuery(string $id)
 * - (Query)deleteQuery(string $id)
 * - (Query)insertQuery(array $usField) 
 * - (Query)updateQuery(string $id, array $usField)
 * - (Query)selectQuery(array $usCond)
 * + (PDOStatement)execute(PDO $dbh, Query $query)
 *
 * [アプリケーションハンガリアン]
 * O/Rマッパーを作ろうとするとどうしても
 * テーブル名やカラム名をパラメタ化しないと難しい。
 * しかし文字列結合によりSQLを作るのは恐ろしすぎる。
 * そこでアプリケーションハンガリアンを採用して
 * 少しでも安全性を高めようとしてみた。
 * 解説：http://bit.ly/9Jp6HB
 *
 * 外から来た安全でない値には、プリフィックス us をつける。
 * 例： 
 * function hoge($usColumn){}
 *
 * 安全とわかっているリテラルや、
 * sFromUsXxx() 的な関数/メソッドにより
 * バリデーションを行った値は安全とみなして
 * プリフィックス s をつける。
 * 例： 
 * $sKey = "rowid";
 * $sColumn = sFromUsColumn($usColumn);
 *
 * //正しい
 * $sKey = $sCol;
 *
 * //正しい
 * $usKey = $usCol;
 *
 * //正しい
 * $sCol = sFromUs($usCol);
 *
 * //これは間違い
 * $sCol = $usCol;
 *
 */
class NekoSchema {
	protected $sTable;
	protected $sPkey;
	protected $sColumns;
	protected $sColumnHash;
	protected $onInsertTimestampColmn;
	protected $onUpdateTimestampColmn;
	public static function getInstance($usTable, $usPkey, $usColumns){
		/*
		 * ORマッパーを作ろうとするとやむを得ないと思うのだが、
		 * $sTable や $sPkey は遺憾ながら文字列結合でSQL構築に使われる。
		 * もしこのクラスを拡張するなら$sTable や $sPkey の安全性に留意せよ。
		 * 安全とfool proofのために、以下の仕様になっている。
		 * - StrictNameRule::isSafe() は名前として m/^[_a-z0-9]+$/i しか受け付けない
		 * - NekoSchema::getInstance() は StrictNameRule をパスした名前しか受け付けない
		 * - NekoSchema::__construct() は protected
		 */
		$sTable		= StrictNameRule::sFromUsName($usTable);
		$sPkey		= StrictNameRule::sFromUsName($usPkey);
		$sColumns	= StrictNameRule::sFromUsColumnArray($usColumns);
		if( $sTable===null || $sPkey===null || $sColumns===null ){
			return null;
		}
		// all green
		return new NekoSchema($sTable, $sPkey, $sColumns);
	}
	protected function __construct($sTable, $sPkey, $sColumns){
		$this->sTable	= $sTable;
		$this->sPkey	= $sPkey;
		$this->sColumns	= $sColumns;
		$this->sColumnHash = array();
		$this->onInsertTimestampColmn = array();
		$this->onUpdateTimestampColmn = array();
		foreach( $this->sColumns as $sCol ){
			$this->sColumnHash[$sCol] = 1;
		}
	}
	public function isValidColumn($usColumn){
		if( strcmp($this->sPkey, $usColumn)===0 ){
			return true;
		}else if( array_key_exists($usColumn, $this->sColumnHash) ){
			return true;
		}
		return false;
	}
	public function sFromUsColumn($usCol){
		$sCol = null;
		if( $this->isValidColumn($usCol) ){
			$sCol = $usCol;
		}
		return $sCol;
	}
	public function sFromUsColsList($usArray){
		$sRet = array();
		foreach( $usArray as $usCol ){
			$sCol = $this->sFromUsColumn($usCol);
			if( $sCol !== null ){
				$sRet[] = $sCol;
			}
		}
		sort($sRet);
		return $sRet;
	}
	public function sColumns(){
		return $this->sColumns;
	}
	public function sTable(){
		return $this->sTable;
	}
	public function sPkey(){
		return $this->sPkey;
	}
	public function addOnInsertTimestamp($usCol){
		$sCol = $this->sFromUsColumn($usCol);
		if( $sCol ){
			$this->onInsertTimestampColmn[$sCol] = 1;
		}
	}
	public function addOnUpdateTimestamp($usCol){
		$sCol = $this->sFromUsColumn($usCol);
		if( $sCol ){
			$this->onUpdateTimestampColmn[$sCol] = 1;
		}
	}
	public function retrieveQuery($usId){
		$sPkey	= $this->sPkey();
		$sTable	= $this->sTable();
		return array(
			"sQuery" => "SELECT * FROM $sTable WHERE $sPkey = ? ",
			"usData" => array($usId));
	}
	public function deleteQuery($usId){
		$sPkey	= $this->sPkey();
		$sTable	= $this->sTable();
		return array(
			"sQuery" => "DELETE FROM $sTable WHERE $sPkey = ? ",
			"usData" => array($usId));
	}
	public function deleteWhereQuery($usCond){
		$sColLs = array();
		$usData = array();
		$sKeys = $this->sFromUsColsList(array_keys($usCond));
		foreach( $sKeys as $sKey ){
			$sColLs[] = "$sKey = ?";
			$usData[] = $usCond[$sKey];
		}
		if( count($sColLs)===0 ){
			return null;
		}
		$sCondStmt = implode(" AND ", $sColLs);

		$sTable = $this->sTable();
		return array(
			"sQuery" => "DELETE FROM $sTable WHERE $sCondStmt",
			"usData" => $usData);
	}
	public function insertQuery($usField){
		$sColLs = array();
		$sPlace = array();
		$usData = array();
		$sKeys	= $this->sFromUsColsList(array_keys($usField));
		foreach( $sKeys as $sKey ){
			$sColLs[] = $sKey;
			$sPlace[] = "?";
			$usData[] = $usField[$sKey];
		}
		foreach( array_diff(array_keys($this->onInsertTimestampColmn), $sKeys) as $sTs ){
			$sColLs[] = $sTs;
			$sPlace[] = "CURRENT_TIMESTAMP";
		}
		if( count($sColLs)===0 ){
			return null;
		}
		$sColStmt= implode(", ", $sColLs);
		$sPlaces = implode(", ", $sPlace);

		$sTable	= $this->sTable();
		return array(
			"sQuery" => "INSERT INTO $sTable ($sColStmt) values ($sPlaces)",
			"usData" => $usData
		);
	}
	public function updateQuery($usId, $usField){
		$sSetLs	= array();
		$usData	= array();
		$sKeys = $this->sFromUsColsList(array_keys($usField));
		foreach( $sKeys as $sKey ){
			$sSetLs[] = "$sKey = ?";
			$usData[] = $usField[$sKey];
		}
		foreach( array_diff(array_keys($this->onUpdateTimestampColmn), $sKeys) as $sTs ){
			$sSetLs[] = "$sTs = CURRENT_TIMESTAMP";
		}
		if( count($sSetLs)===0 ){
			return null;
		}
		$usData[] = $usId;
		$sSetStmt = implode(", ", $sSetLs);

		$sPkey	= $this->sPkey();
		$sTable	= $this->sTable();
		return array(
			"sQuery" => "UPDATE $sTable SET $sSetStmt WHERE $sPkey = ?",
			"usData" => $usData
		);
	}
	public function selectQuery($usCond){
		$sColLs = array();
		$usData = array();
		$sKeys = $this->sFromUsColsList(array_keys($usCond));
		foreach( $sKeys as $sKey ){
			$sColLs[] = "$sKey = ?";
			$usData[] = $usCond[$sKey];
		}
		if( count($sColLs)===0 ){
			return null;
		}
		$sCondStmt = implode(" AND ", $sColLs);

		$sTable	= $this->sTable();
		return array(
			"sQuery" => "SELECT * FROM $sTable WHERE $sCondStmt",
			"usData" => $usData
		);
	}
	public function selectAllQuery(){
		$sTable	= $this->sTable();
		return array(
			"sQuery" => "SELECT * FROM $sTable",
			"usData" => array()
		);
	}
	public static function userSqlQuery($usUserSql, $usData){
		//XXX sQuery に $usUserSql が代入されている。
		//ユーザが書いた任意のSQLを受けるためのメソッドなので仕方ない。
		return array(
			"sQuery" => $usUserSql,
			"usData" => $usData
		);
	}
	public static function execute($dbh, $query){
		if( $query && $sth = $dbh->prepare($query['sQuery']) ){
			if( $sth->execute($query['usData']) ){
				return $sth;
			}
		}
		return null;
	}
}

class NekoStruct {
	protected $field;
	public function __construct($field){
		$this->field = $field;
	}
	public function __get( $key ) {
		return $this->field[$key];
	}
}
/*
 * + (NekoRow)insertedInstance(PDO $dbh, NekoSchema $schema, string $id)
 * + (NekoRow)selectedInstance(PDO $dbh, NekoSchema $schema, array $rec)
 * - (array)retrieve()
 * - (int)delete()
 * - (int)update()
 */
class NekoRow {
	protected $id;
	protected $dbh;
	protected $schema;
	protected $cache;
	protected $set_table;

	protected function __construct($dbh, $schema) {
		$this->dbh = $dbh;
		$this->schema = $schema;
		$this->cache = null;
		$this->set_table = array();
	}

	public static function insertedInstance($dbh, $schema, $id){
		$ret = new NekoRow($dbh, $schema);
		$ret->id = $id;
		return $ret;
	}
	public static function selectedInstance($dbh, $schema, $rec){
		$ret = new NekoRow($dbh, $schema);
		$ret->id = $rec[$schema->sPkey()];
		$ret->cache = $rec;
		return $ret;
	}
	public function __get( $key ) {
		if( array_key_exists($key, $this->set_table ) ){
			return $this->set_table[$key];
		}
		if( strcmp($key, $this->schema->sPkey()) === 0 ){
			return $this->id;
		}
		if( $this->cache === null ){
			$this->cache = $this->retrieve();
		}
		return $this->cache[ $key ];
	}

	public function __set( $key, $value ) {
		$this->set_table[$key] = $value;
		return $this;
	}
	public function retrieve(){
		$query = $this->schema->retrieveQuery($this->id);
		$sth = NekoSchema::execute($this->dbh, $query);
		if( $row = $sth->fetch(PDO::FETCH_ASSOC) ){
			return $row;
		}
		return null;
	}
	public function delete(){
		$query = $this->schema->deleteQuery($this->id);
		$sth = NekoSchema::execute($this->dbh, $query);
		if( $sth === null ){
			$info = $this->dbh->errorInfo();
			throw new Exception($info[2]);
		}
		return $sth->rowCount();
	}
	public function update(){
		$query = $this->schema->updateQuery($this->id, $this->set_table);
		$sth = NekoSchema::execute($this->dbh, $query);
		if( $sth === null ){
			$info = $this->dbh->errorInfo();
			throw new Exception($info[2]);
		}
		return $sth;
	}
	public function beginTransaction(){
		$this->dbh->beginTransaction();
	}
	public function commit(){
		$this->dbh->commit();
	}
	public function rollBack(){
		$this->dbh->rollBack();
	}
}

/*
 * - (NekoTable) new(PDO $dbh, NekoSchema $schema)
 * - (array)search(array $cond)
 * - (array)search_by_sql(string $sql, array $values, NekoSchema $schema);
 * - (NekoRow)retrieve(string $id)
 * - (NekoRow)insert(array $field)
 */
class NekoTable {
	protected $dbh;
	protected $schema;
	public function __construct($dbh, $schema){
		$this->dbh = $dbh;
		$this->schema = $schema;
	}
	public function search($usCond){
		$query = $this->schema->selectQuery($usCond);
		$sth = NekoSchema::execute($this->dbh, $query);
		$ret = array();
		while( $rec = $sth->fetch(PDO::FETCH_ASSOC) ){
			$ret[] = NekoRow::selectedInstance($this->dbh, $this->schema, $rec);
		}
		return $ret;
	}
	public function first($cond){
		$ret = $this->search($cond);
		if( count($ret) === 0 ){
			return null;
		}
		return $ret[0];
	}
	public function modify_by_sql($userSql, $data){
		$query = NekoSchema::userSqlQuery($userSql, $data);
		return NekoSchema::execute($this->dbh, $query);
	}
	public function search_by_sql($userSql, $data, $schema=null){
		$query = NekoSchema::userSqlQuery($userSql, $data);
		$sth = NekoSchema::execute($this->dbh, $query);
		$ret = array();
		while( $rec = $sth->fetch(PDO::FETCH_ASSOC) ){
			if( $schema === null ){
				$ret[] = new NekoStruct($rec);
			}else{
				$ret[] = NekoRow::selectedInstance($this->dbh, $schema, $rec);
			}
		}
		return $ret;
	}
	public function retrieve($id){
		$query = $this->schema->retrieveQuery($id);
		$sth = NekoSchema::execute($this->dbh, $query);
		if( $rec = $sth->fetch(PDO::FETCH_ASSOC) ){
			return NekoRow::selectedInstance($this->dbh, $this->schema, $rec);
		}
		return null;
	}
	public function all(){
		$query = $this->schema->selectAllQuery();
		$sth = NekoSchema::execute($this->dbh, $query);
		$ret = array();
		while( $rec = $sth->fetch(PDO::FETCH_ASSOC) ){
			$ret[] = NekoRow::selectedInstance($this->dbh, $this->schema, $rec);
		}
		return $ret;
	}
	public function insert($field){
		$query = $this->schema->insertQuery($field);
		if( NekoSchema::execute($this->dbh, $query) === null ){
			$info = $this->dbh->errorInfo();
			throw new Exception($info[2]);
		}
		$id = $this->dbh->lastInsertId();
		return NekoRow::insertedInstance($this->dbh, $this->schema, $id);
	}
	public function delete_where($cond){
		$query = $this->schema->deleteWhereQuery($cond);
		$sth =  NekoSchema::execute($this->dbh, $query);
		if( $sth === null ){
			$info = $this->dbh->errorInfo();
			throw new Exception($info[2]);
		}
		return $sth;
	}
	public function find_or_create($field){
		$ret = $this->first($field);
		if( $ret === null ){
			$ret = $this->insert($field);
		}
		return $ret;
	}
	public function beginTransaction(){
		$this->dbh->beginTransaction();
	}
	public function commit(){
		$this->dbh->commit();
	}
	public function rollBack(){
		$this->dbh->rollBack();
	}
}
