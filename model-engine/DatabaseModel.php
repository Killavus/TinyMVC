<?php
/** A model class retrieving data from a database.
  * @author Killavus
  * @package Killavus
  * @category Model
  * @version 0.1
  */
require_once 'AbstractModel.php';
require_once 'LazyConnectionsTable.php';
require_once 'DatabaseSearchEngine.php';

abstract class DatabaseModel extends AbstractModel {
  /** A boolean representing whether a model should make transaction for all update/save/delete actions. Useful when we're grouping saves. Default it is true.
    * @returns boolean
    * @since 0.2
    */
  protected $transactioning;
	/** Storing an actual database connection.
	  * @returns PDO
	  * @since 0.1
	  */
	protected $database;
   
   /** Used to store a table name. If NULL, it's computed by the convention.
     * @returns string
     * @since 0.2
     */
   protected $uniqTableName;
	
	/** Constructs a database model.
	  * @see AbstractClass::__construct
	  * @since 0.1
	  */
	public function __construct( $id = NULL ) {
		global $g_database;
		
    spl_autoload_register( Array( get_class($this), 'autoload' ) );
		$this->database = $g_database->handler();      
    $this->transactioning = TRUE;
		parent::__construct( $id );
	}
  
  /** Loads models automatically.
    * @param string $className A class name of class being constructed.
    * @since 0.2
    */
  protected static function autoload( $className ) {
    $className = ucfirst($className);    
    if( file_exists( MVC_MAIN_DIRECTORY . MVC_MODELS . $className . '.php' ) ) 
      require_once MVC_MAIN_DIRECTORY . MVC_MODELS . $className . '.php';
  }
	
	/** Changes an actual database connection to another one. Returns TRUE if database is changed - FALSE otherwise.
	  * @param PDO $pdo A database object (PDO one).
	  * @returns boolean
	  * @since 0.1
	  */
	public function changeDatabase( $pdo ) {
		if( $this->transactioning ) $this->database->commit();
		unset($this->database);
		if( $pdo instanceof PDO ) {
			$this->database = $pdo;
			return TRUE;
		}
		else return FALSE;
	}
	
	/** Loads data from a database. Returns TRUE if load is successful - FALSE otherwise.
	  * @param integer $id ID of the record in a database.
	  * @param boolean $recurrent <strong>Currently not implemented.</strong> Determines whether a recurrent way of loading should be used. Default FALSE.
	  * @throws DatabaseException When query is not completed and an database error occured.
	  * @returns boolean
	  * @since 0.1
	  */
	public function load( $id, $recurrent = FALSE ) {
		$table = $this->tableName();
		$tableRows = count( self::$structure[get_class($this)] );
		$connectionCount = count( self::$connections );
		$sqlString = "SELECT ";
		$connectionRows = Array();
		$fetchedRows = Array();
    $parentColumn = null;
    
		/* Getting rows to fetch from a structure of data: */
		foreach( self::$structure[get_class($this)] as $key => $dataRow ) $fetchedRows[] = $table . "." . $dataRow[0];
		/* Getting rows to fetch from connections of data: */
		foreach( self::$connections[get_class($this)] as $key => $connection ) {
			@list( $connectionTable, $connectionType, $parentColumn, $connectionColumn ) = $connection;
			$subTable = $table . '_' . $this->tableName($connectionTable);
			
			switch( $connectionType ) {
				case ConnectionType::ONE_TO_ONE:
					$fetchedRows[] = $table . "." . ($parentColumn? $parentColumn : (strtolower($connectionTable) . '_id'));
					break;
				case ConnectionType::ONE_TO_MANY:
				case ConnectionType::MANY_TO_MANY:
					$connectionRows[] = Array( ($connectionColumn? $connectionColumn : (strtolower($connectionTable) . '_id')), $connectionColumn? TRUE : FALSE );
					$fetchedRows[] = $subTable . "." . ($connectionColumn? $connectionColumn : (strtolower($connectionTable) . '_id'));
					break;
				default:
					continue;
					break;
			}
		}
		
		$sqlString .= join( ", ", $fetchedRows );
		$sqlString .= " FROM " . $table;
		/* Making necessary INNER JOINs: */
		foreach( self::$connections[get_class($this)] as $key => $connection ) {
			@list( $connectionTable, $connectionType, $parentColumn, $connectionColumn ) = $connection;
			$subTable = $table . '_' . $this->tableName($connectionTable);
			$modelName = strtolower(get_class($this));
      
      $parentName = $parentColumn? $parentColumn : ( $modelName . '_id' );
			
			switch( $connectionType ) {
				case ConnectionType::ONE_TO_MANY:
				case ConnectionType::MANY_TO_MANY:
					$connectionTableName = $this->tableName( $connectionTable );
					$sqlString .= " LEFT JOIN " . $subTable . " ON " . $subTable . "." . $parentName . " = " . $table . ".id";
					break;
				default:
					continue;
					break;
			}
		}
		$sqlString .= " WHERE " . $table . ".id = ?";
		
		$sql = $this->database->prepare( $sqlString );
		$sql->execute( Array( $id ) );
		$ret = $sql->fetchAll( PDO::FETCH_ASSOC );
		
		/* Query encounters an error while executing: */
		if( $sql->errorCode() != "00000" ) {
			$errorMessage = $sql->errorInfo();
			throw new DatabaseException( "SQL Execution Error: [" . $errorMessage[0] . "] " . $errorMessage[2] );
			return FALSE;
		}
		else {
			if( !count( $ret ) ) return FALSE;
			else {
				// Creating lazy tables:
				foreach( $connectionRows as $row ) {
          list($row, $unique) = $row; // Drugi parametr to bool sprawdzajacy, czy przekazalismy 'policzona' wartosc, czy tylko niepoliczona - aktualnie nie ma uzycia
					list( $rowName ) = explode( "_id", $row );
					$modelName = ucfirst($rowName);
					$rowName = $this->tableName($rowName);
          if( !$unique )
            $this->data[$rowName] = new LazyConnectionsTable( $modelName, ucfirst(get_class($this)) );
          else {
            foreach( self::$connections[get_class($this)] as $connection ) {
              @list( $connectionTable, $connectionType, $parentColumn, $connectionColumn ) = $connection;
              if( stripos( $connectionColumn, $row ) === 0 ) {
                list( $parentColumn ) = explode( '_id', $parentColumn );
                $this->data[$rowName] = new LazyConnectionsTable( $modelName, ucfirst($parentColumn) );
              }
            }
          }            
					$this->data[$rowName]->disableVirtualMode();
					$this->data[$rowName]->disableLoading();
				}
        				
				$firstRow = $ret[0];
				// Getting a structure data:
				foreach( self::$structure[get_class($this)] as $key => $structure ) $this->data[$structure[0]] = $firstRow[$structure[0]];
				// Getting connection IDs:
				foreach( $ret as $row ) {
					foreach( $connectionRows as $connectionRow ) {
            list( $connectionRow ) = $connectionRow; // Drugi parametr to bool sprawdzajacy, czy przekazalismy 'policzona' wartosc, czy tylko niepoliczona - aktualnie nie ma uzycia
						list( $connectionName ) = explode( '_id', $connectionRow );
						$connectionName = $this->tableName($connectionName);
						if( $row[$connectionRow] ) {
							global $g_debug;
							$adding = true;
							for( $i = 0; $i < count($this->data[$connectionName]); ++$i ) {
								if( $row[$connectionRow] == $this->data[$connectionName][$i] ) {
									$adding = false;
									break;
								}
							}
							
							if( $adding ) $this->data[$connectionName][] = $row[$connectionRow];							
						}
					}
				}
        
        // Getting all one-to-one connections using single SQL queries:
        foreach( self::$connections[get_class($this)] as $connection ) {
          @list( $connectionTable, $connectionType, $parentColumn, $connectionColumn ) = $connection;          
          switch( $connectionType ) {
            case ConnectionType::ONE_TO_ONE:
              list( $modelName ) = explode( '_id', $parentColumn? $parentColumn : ( $connectionTable . '_id' ) );
              $p = new $connectionTable( $firstRow[ $parentColumn? $parentColumn : ( $connectionTable . '_id' ) ] );
              $this->data[$modelName] = $p;
            break;
            default: continue; break;
          }
        }     
				
				// Enabling a virtual mode on datatables:
				foreach( $connectionRows as $row ) {
          list($row) = $row; // Drugi parametr to bool sprawdzajacy, czy przekazalismy 'policzona' wartosc, czy tylko niepoliczona - aktualnie nie ma uzycia
					list( $rowName ) = explode( "_id", $row );
					$rowName = $this->tableName($rowName);
					$this->data[$rowName]->enableVirtualMode();
					$this->data[$rowName]->enableLoading();
				}
			}
		}
		return TRUE;
	}
	
	/** Additional method used to determine conventional table name.
	  * @param NULL|string $name A model name used to create a table name from it. If NULL, actual class' name is assumed.
	  * @returns string
	  * @since 0.1
	  */
	public function tableName($name = NULL) {
    if( !$name ) $name = get_class($this);
    $o = new $name();
    if( $o->uniqTableName ) return $o->uniqTableName;
    else {
      $modelName = strtolower( $name );
      $lastCharacter = $modelName[strlen($modelName)-1];
      return in_array( $lastCharacter, Array( 's','h' ) )? $modelName . "es" : $modelName . "s";    
    }
	}
	
	/** Starts the searching function.
	  * @returns DatabaseSearchEngine
	  * @see DatabaseSearchEngine
	  * @since 0.1
	  */
	public function search() {
      return new DatabaseSearchEngine( $this->database, get_class($this), $this->uniqTableName );
	}
	
	/** Saves changes or inserts a virtual row in to a database. Returns true if it's done with success.
	  * @returns boolean
    * @throws DatabaseException When a PDO or SQL error occured.
	  * @since 0.1
	  */
	public function save( $id=NULL ) {
		if( $this->virtual ) {
      $sqlString = "INSERT INTO " . $this->tableName();
      $columnsList = Array();
      $valuesList = Array();
      $quotationsList = Array();
      
      // constructing a list of columns and a list of values:
      foreach( self::$structure[get_class($this)] as $structureRow ) {
        list( $structureName ) = $structureRow;
        if( isSet( $this->data[$structureName] ) and !empty( $this->data[$structureName] ) ) {
					$columnsList[] = $structureName;
					$val = null;
					if( !is_numeric($this->data[$structureName]) and !is_array($this->data[$structureName]) and !is_bool($this->data[$structureName]) and strpos( $this->data[$structureName], "'" ) !== FALSE ) $val = Tools::postgresPrepare( $this->data[$structureName] );
					else $val = $this->data[$structureName];
					
					$valuesList[] = $val;
          $quotationsList[] = "?";
        }
      }
     	 
      $sqlString .= '(' . join( ", ", $columnsList ) . ') VALUES( ' . join( ", ", $quotationsList ) .')';

      $this->data["id"] = $this->insertWithReturnID( $sqlString, $valuesList );                        
      $this->virtual = $this->data["id"]? FALSE : TRUE;
        
      return !$this->virtual;
		}
		else {
			$bools = Array();
			$arrays = Array();
			$columns = Array();
			$sqlString = 'UPDATE ' . $this->tableName() . ' SET ';

			foreach( self::$structure[get_class($this)] as $struct ) {
				list( $columnName ) = $struct;
				$columns[] = $columnName . ' = ?';
			}
			$sqlString .= join( ', ', $columns );
			$sqlString .= ' WHERE id = ?';
			$retArr = Array();
			foreach( $columns as $key => $column ) {
				list( $foo ) = explode( ' = ?', $column );        
				if( is_bool($this->data[$foo]) ) $bools[] = Array( $key+1, $this->data[$foo] );
				else if( is_array($this->data[$foo]) ) $arrays[] = Array( $key+1, $this->data[$foo] );
				else $retArr[$key] = $this->data[$foo];
			}
			$retArr[count(self::$structure[get_class($this)])] = $this->data['id'];

			try {
				if( $this->transactioning ) $this->database->beginTransaction();
				$sql = $this->database->prepare( $sqlString );
        foreach( $retArr as $key => $ret ) {
          $sql->bindValue( $key+1, $ret );
        }
        foreach( $bools as $boolVals ) {
          list( $index, $val ) = $boolVals;
          $sql->bindValue( $index, $val, PDO::PARAM_BOOL );
				}
				foreach( $arrays as $arrVals ) {
					list( $index, $val ) = $arrVals;
					$val = "{".join(',', $quoted)."}";
					$sql->bindValue( $index, $val );
				}
				$sql->execute();
				if( $this->transactioning ) $this->database->commit();
				if( $sql->errorCode() != "00000" ) {
					$errorMessage = $sql->errorInfo();
					throw new DatabaseException( "SQL Execution Error: [" . $errorMessage[0] . "] " . $errorMessage[2] );
					return FALSE;
				}
			}	
			catch( PDOException $pdo ) {
          if( $this->transactioning )	$this->database->rollBack();
					throw new DatabaseException( 'PDO Execution Error: ' . $pdo->getMessage() );
					return FALSE;
			}
			return TRUE;
		}
	}
	
	/** Deletes a record from the database. Returns true if it's done with success.
	  * @returns boolean
	  * @since 0.1
	  */
	public function delete() {
		if( !$this->loaded() ) return FALSE;
		$tableName = $this->tableName();
		try {
			if( $this->transactioning ) $this->database->beginTransaction();
			$sql = $this->database->prepare( "DELETE FROM " . $tableName . ' WHERE id = ?' );
			if( !DATABASE_TRIGGERED ) {
				foreach( $this->data as $key => $data ) {
					$ret = $this->verifyType($key,$data);
					if( !$ret ) continue;
					list($type,$column) = $ret;
					$connectionTable = $tableName . '_' . $this->tableName($column);
					$modelColumn = strtolower( get_class( $this ) ) . '_id';
				
					$data->disableLoading();
					
					$ids = Array();
					foreach( $data as $id ) $ids[] = $id;

					$connectionSql = $this->database->prepare( 'DELETE FROM ' . $connectionTable . ' WHERE ' . $modelColumn . ' = ?' );
					$connectionSql->execute( Array( $this->__get('id') ) );
					if( $type == ConnectionType::ONE_TO_MANY ) {
						$connectionTableSql = $this->database->prepare( 'DELETE FROM ' . $key . ' WHERE id IN( ' . join( ',', $ids ) . ')' );
						$connectionTableSql->execute();
					}						
				}
			}
			$sql->execute( Array( $this->__get('id') ) );
			if( $this->transactioning ) $this->database->commit();			
		}
		catch( PDOException $pdo ) {
			if( $this->transactioning ) $this->database->rollBack();
			throw new ModelException( 'SQL error while deleting ' . get_class($this) . ' with ID: ' . $this->__get('id') . ': ' . $pdo->getMessage() );
			return FALSE;
		}
		return TRUE;
	}
	
	/** Verifies whether the given type is a lazy table or not. Used in deleting queries from the database.
	  * @param string $key A key in a $data table.
	  * @parem mixed $data A given data.
	  * @returns Array
	  * @since 0.1
	  */
	private function verifyType($key,$data) {
		if( !($data instanceof LazyConnectionsTable ) ) return FALSE;
		foreach( self::$connections[get_class($this)] as $connection ) {
			list( $connectionName, $connectionType ) = $connection;
			$connectionTable = $this->tableName( $connectionName );
			if( $connectionTable == $key ) return Array( $connectionType, $connectionName );
		}
		return FALSE;
	}
  
  /** Sets a transactioning boolean.
    * @param boolean $val A value passed to a transactioning boolean.
    * @since 0.2
    */
  public function setTransactioning( $val ) {
    $this->transactioning = $val;
  }  
  
  protected function insertWithReturnID( $query, $valuesList ) {
    $postgres = strpos( DATABASE_PDO, "pgsql:" ) === 0;
    $mysql = strpos( DATABASE_PDO, "mysql:" ) === 0 or strpos( DATABASE_PDO, "mysqli:" ) === 0;
    $sqlite = strpos( DATABASE_PDO, "sqlite:" ) === 0;
    
    $returnID = NULL;
    
    try {
      if( $this->transactioning ) $this->database->beginTransaction();     
      
      // PostgreSQL is using RETURNING for obtaining an inserted ID - it's the most elegant solution!
      if( $postgres ) $query .= " RETURNING id";

      $sql = $this->database->prepare( $query );
      $sql->execute( $valuesList );      
      
      if( $sql->errorCode() != "00000" ) {
        $errorMessage = $sql->errorInfo();
        if( $this->transactioning ) $this->database->rollBack();
        throw new DatabaseException( "SQL Execution Error: [" . $errorMessage[0] . "] " . $errorMessage[2] );
        return $returnID;
      }
      
      // Now we're obtaining returned ID like a SELECT fetched data [PostgreSQL]:
      if( $postgres ) {
        $data = $sql->fetch( PDO::FETCH_ASSOC );
        $returnID = $data["id"];
      }
      else if( $mysql or $sqlite ) $returnID = $this->database->lastInsertId();      
      
      if( $this->transactioning ) $this->database->commit();      
    }
    catch( PDOException $pdo ) {
       if( $this->transactioning ) $this->database->rollBack();
       throw new DatabaseException( "PDO Exception: [" . $pdo->getCode() . "] " . $pdo->getMessage() );
       return $returnID;
    }
    
    return $returnID;
  }
  
  public function recombine( $str, $arr, $i = 0 ) {
    if( preg_replace( '/\?/', $this->database->quote($arr[$i]), $str, 1 ) == $str ) return $str;
    else {
      $str = preg_replace( '/\?/', $this->database->quote($arr[$i]), $str, 1 );
      $i += 1;
      return $this->recombine( $str, $arr, $i );
    }
  }
}
?>
