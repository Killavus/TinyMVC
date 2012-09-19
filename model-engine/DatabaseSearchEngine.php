<?php
/** A database (SQL) implementation of a search engine.
  * @author Killavus
  * @package Killavus
  * @category Model
  * @version 0.1
  */
  
require_once 'SearchEngine.php';
require_once 'LazyConnectionsTable.php';

class DatabaseSearchEngine extends SearchEngine {
	/** Storing an actual database connection.
	  * @returns PDO
	  * @since 0.1
	  */
	protected $database;
	/** Storing a SQL query.
	  * @returns string
	  * @since 0.1
	  */
	protected $sqlQuery;
	/** Table name used for searching.
	  * @returns string
	  * @since 0.1
	  */
	protected $searchName;
	
	/** A pointer specifying the last used function with arguments.
	  * @returns reference
	  * @since 0.1
	  */
	protected $actualGlue;
  
  /** An unique table name specified by a model. Use with care!
    * @returns string
    * @since 0.2
    */
  protected $uniqTableName;
  
  /** Defines a start glue when no comparsion is performed before gluing.
    * @returns string
    * @since 0.1
    */
	protected $startGlue;

	/** An array storing a sequence of an elements.
	 * @returns string
	 * @since 0.2
	 */
	protected $elementsSequence;
  
   /** Loads models automatically.
    * @param string $className A class name of class being constructed.
    * @since 0.2
    */
  protected static function autoload( $className ) {
    $className = ucfirst($className);
    if( file_exists( MVC_MAIN_DIRECTORY . MVC_MODELS . $className . '.php' ) ) 
      require_once MVC_MAIN_DIRECTORY . MVC_MODELS . $className . '.php';
  }
	
	/** Constructs a new search engine object.
	  * @param PDO $dbHandler Database handler used for executing a statement.
	  * @param string $searchName Table name used for searching.
    * @param string $uniqTableName An unique table name specified by a model. Use with care!
	  * @since 0.2
	  */
	public function __construct( $dbHandler, $searchName, $uniqTableName ) {
    spl_autoload_register( Array( get_class($this), 'autoload' ) );
		$this->searchName = $searchName;    
		$this->database = $dbHandler;
    $this->uniqTableName = $uniqTableName;
    $this->actualGlue = NULL;
    $this->startGlue = Array();
		$this->sqlQuery = Array();
		$this->elementsSequence = Array();
	}
	
	/** Performs a 'less than' comparsion.
	  * @returns this
	  * @param array $arr Stores a ( column, value ) pair. Value can be a ':identifier' for PDO prepared statement. It has to be binded using bindValues function.
	  * @param boolean $weak Determines whether an inequality is weak (<=) or strong (<). Defaults to FALSE (which means the inequality is strong).
	  * @since 0.1
	  */
	public function lessThan( $arr, $weak = FALSE ) {
		$indexName = $weak? 'lessThanEq' : 'lessThan';
		
		if( !isSet( $this->sqlQuery[$indexName] ) ) $this->sqlQuery[$indexName] = Array();
		$this->sqlQuery[$indexName][] = $arr;
		$this->actualGlue = &$this->sqlQuery[$indexName][count($this->sqlQuery[$indexName])-1];
		$this->elementsSequence[] = Array( &$this->sqlQuery[$indexName][count($this->sqlQuery[$indexName])-1], $indexName );	
		return $this;
	}

	/** Performs a 'more than' comparsion.
	  * @returns this
	  * @param array $arr Stores a ( column, value ) pair. Value can be a ':identifier' for PDO prepared statement. It has to be binded using bindValues function.
	  * @param boolean $weak Determines whether an inequality is weak (>=) or strong (>). Defaults to FALSE (which means the inequality is strong).
	  * @since 0.1
	  */
	public function moreThan( $arr, $weak = FALSE ) {
		$indexName = $weak? 'moreThanEq' : 'moreThan';
		
		if( !isSet( $this->sqlQuery[$indexName] ) ) $this->sqlQuery[$indexName] = Array();
		$this->sqlQuery[$indexName][] = $arr;
		$this->actualGlue = &$this->sqlQuery[$indexName][count($this->sqlQuery[$indexName])-1];
		$this->elementsSequence[] = Array( &$this->sqlQuery[$indexName][count($this->sqlQuery[$indexName])-1], $indexName );	
		return $this;	
	}
	
	/** Checks whether a column is equal to sth.
	  * @returns this
	  * @param array $arr Stores a ( column, value ) pair. Value can be a ':identifier' for PDO prepared statement. It has to be binded using bindValues function.
	  * @since 0.1
	  */
	public function equal( $arr ) {
		$indexName = 'equal';
		
		if( !isSet( $this->sqlQuery[$indexName] ) ) $this->sqlQuery[$indexName] = Array();
		$this->sqlQuery[$indexName][] = $arr;
		$this->actualGlue = &$this->sqlQuery[$indexName][count($this->sqlQuery[$indexName])-1];
		$this->elementsSequence[] = Array( &$this->sqlQuery[$indexName][count($this->sqlQuery[$indexName])-1], $indexName );	
		return $this;
	}
	
	/** Checks whether a column is in specified set.
	  * @returns this
	  * @param array $arr Stores a ( column, value ) pair. Value can be a ':identifier' for PDO prepared statement. It has to be binded using bindValues function.
	  * @since 0.1
	  */
	public function in( $arr ) {
		$indexName = 'in';
		
		if( !isSet( $this->sqlQuery[$indexName] ) ) $this->sqlQuery[$indexName] = Array();
		$this->sqlQuery[$indexName][] = $arr;
		$this->actualGlue = &$this->sqlQuery[$indexName][count($this->sqlQuery[$indexName])-1];
		$this->elementsSequence[] = Array( &$this->sqlQuery[$indexName][count($this->sqlQuery[$indexName])-1], $indexName );	
		return $this;	
	}

	/** Checks whether a column is in specified set.
	  * @returns this
	  * @param array $arr Stores a ( column, value ) pair. Value can be a ':identifier' for PDO prepared statement. It has to be binded using bindValues function.
	  * @since 0.1
	  */	
	public function between( $arr ) {
		$indexName = 'between';
		
		if( !isSet( $this->sqlQuery[$indexName] ) ) $this->sqlQuery[$indexName] = Array();
		$this->sqlQuery[$indexName][] = $arr;
		$this->actualGlue = &$this->sqlQuery[$indexName][count($this->sqlQuery[$indexName])-1];
		$this->elementsSequence[] = Array( &$this->sqlQuery[$indexName][count($this->sqlQuery[$indexName])-1], $indexName );	
		return $this;	
	}
	
	/** Glues a left closure ("(") at the BEGINNING of the last query part.
	  * @returns this
	  * @since 0.1
	  */
	public function _lCl() {
		if( $this->actualGlue ) $this->actualGlue['lClGlued'] = TRUE;
    else $this->startGlue['lClGlued'] = TRUE;
		return $this;		
	}
	
	/** Glues a right closure (")") at the END of the last query part.
	  * @returns this
	  * @since 0.1
	  */
	public function _rCl() {
		$this->actualGlue['rClGlued'] = TRUE;
		return $this;		
	}
	
	/** Glues an OR operator at the END of the last query part.
	  * @returns this
	  * @since 0.1
	  */
	public function _or() {
		$this->actualGlue['orGlued'] = TRUE;
		return $this;		
	}
	
	/** Glues a NOT operator at the BEGINNING of the last query part.
	  * @returns this
	  * @since 0.1
	  */
	public function _not() {
		if( $this->actualGlue ) $this->actualGlue['notGlued'] = TRUE;
    else $this->startGlue['notGlued'] = TRUE;    
		return $this;
	}
	
	
	/** Limits a result set size. BEWARE: It's unglueable.
	  * @returns this
	  * @param array|integer $data If it's an array, it stores a (pos, length) pair specifying a range of our interest. If it's an integer, it limits only to a certain first n values.
	  * @since 0.1
	  */
	public function limitTo( $data ) {
		$this->sqlQuery['limitTo'] = $data;
		return $this;
	}
	
	/** Performs a 'like' comparsion.
	  * @returns this
	  * @param array $arr Stores a ( column, value ) pair. Value can be a ':identifier' for PDO prepared statement. It has to be binded using bindValues function.
	  * @since 0.1
	  */
	  
	public function like( $arr ) {
		$indexName = 'equal';
		
		if( !isSet( $this->sqlQuery[$indexName] ) ) $this->sqlQuery[$indexName] = Array();
		$this->sqlQuery[$indexName][] = $arr;
		$this->actualGlue = &$this->sqlQuery[$indexName][count($this->sqlQuery[$indexName])-1];
		$this->elementsSequence[] = Array( &$this->sqlQuery[$indexName][count($this->sqlQuery[$indexName])-1], $indexName );	
		return $this;	
	}
	
	/** Orders a result set using specified rules. BEWARE: It's unglueable.
	  * @param array $arr Stores an ordering rules (for example: id ASC) in order of its urgency.
	  * @returns this
	  * @since 0.1
	  */
	public function orderBy( $arr ) {
		$this->sqlQuery['orderRules'] = $arr;
		return $this;
	}
	
	/** Performs a raw query. Use with care!
	  * @param string $query Query to be prepared. You can use ':identifier' - but bind an arguments using bindValues.
	  * @returns this
	  * @since 0.1
	  */
	public function rawQuery( $query ) {
		$this->sqlQuery['raw'] = $query;
		return $this;
	}

	/** Specifies whether values should be distinct or not.
	 * @returns this
	 * @since 0.2
	 */
	public function toggleDistinct() {
		if( !isSet( $this->sqlQuery['distinct'] ) or $this->sqlQuery['distinct'] == false ) $this->sqlQuery['distinct'] = true;
		else $this->sqlQuery['distinct'] = false;
	}
	
	/** If you're using PDO :identifiers for a query, now it's time for binding them!
	  * @param array $arr An array with pairs (identifier, value).
	  * @returns this
	  * @since 0.1
	  */
	public function bindValues( $arr ) {
		$this->sqlQuery['bindedValues'] = $arr;
      return $this;
	}
	
	/** Composes a query.
	  * @returns null|PDOStatement|array
	  * @throws DatabaseException When a query is invalid or PDO exception occured.
	  * @since 0.1
	  */
	public function start() {
		if( isSet( $this->sqlQuery['raw'] ) ) {
			try {
				$sql = $this->database->prepare( $this->sqlQuery['raw'] );
				if( isSet( $this->sqlQuery['bindedValues'] ) ) $sql->execute( $this->sqlQuery['bindedValues'] );
				else $sql->execute();
				
				if( $sql and $sql->errorCode() != "00000" ) {
					$ret = $sql->errorInfo();
					list( $errorCode, $garbage, $errorMessage ) = $ret;
					throw new DatabaseException( 'SQL exception: [' . $errorCode . '] ' . $errorMessage );
					return NULL;
				}
        return $sql;
			}
			catch( PDOException $pdo ) {
				throw new DatabaseException( 'PDO exception: ' . $pdo->getMessage() );
				return NULL;
			}
		}
		else {
			$sql = "SELECT " . ( @$this->sqlQuery['distinct']? "DISTINCT " : "" ) . "id FROM " . $this->tableName($this->searchName);
			$linkers = Array(
				'moreThan' => '>',
				'moreThanEq' => '>=',
				'equal' => '=',
				'lessThan' => '<',
				'lessThanEq' => '<=',
				'like' => 'LIKE'
			);

			$formulas = count( $this->elementsSequence );
			if( $formulas > 0 ) $sql .= " WHERE";

			if( @$this->startGlue['notGlued'] ) $sql .= " NOT";
			if( @$this->startGlue['lClGlued'] ) $sql .= " (";

			foreach( $this->elementsSequence as $position => $formula ) {
				list( $data, $index ) = $formula;
				$key = $data[0]; $value = $data[1];
				// Value validation:
				if( is_bool($value) ) {
					if( $value === true ) $value = 'true';
					else if( $value === false ) $value = 'false';
				}

				if( !is_numeric($value) && !is_bool($value) && !is_array($value) ) {
					if( strpos( ":", $value ) !== 0 ) {
						$value = $this->database->quote($value);
					}
				}

				if( @$data['notGlued'] ) $sql .= ' NOT';
				if( @$data['lClGlued'] ) $sql .= ' (';

				if( $index != 'between' && $index != 'in' ) {
					$sql .= " " . $key . " " . $linkers[$index] . " " . $value;
				}
				else {
					if( $index == 'between' ) $sql .= " " . $data[0] . ' BETWEEN ' . $data[1][0] . ' AND ' . $data[1][1];
					if( $index == 'in' ) {
						$elems = is_array($value)? count( $value ) : 0;
						if( $elems > 0 ) $sql .= " " . $key . " IN(" . join( ',', $value ) . ')';
						else {
							return new LazyConnectionsTable( $this->searchName );
						}
					}
				}

				if( @$data['rClGlued'] ) $sql .= ' )';

				$linkWith = ' AND';
				if( @$data['orGlued'] ) $linkWith = ' OR';

				if( $position != ($formulas-1) ) $sql .= $linkWith;
			}

			if( @$this->sqlQuery['orderBy'] ) 
				$sql .= ' ORDER BY ' . join( ', ', $this->sqlQuery['orderBy'] );
			if( @$this->sqlQuery['limitTo'] ) {
				if( is_array($this->sqlQuery['limitTo']) ) $sql .= ' LIMIT ' . join( ',', $this->sqlQuery['limitTo'] );
				else $sql .= ' LIMIT ' . $this->sqlQuery['limitTo'];
			}

			$sqlQ = $this->database->prepare( $sql );
			if( @$this->sqlQuery['bindedValues'] ) $sqlQ->execute( $this->sqlQuery['bindedValues'] );
			else $sqlQ->execute();

			$p = new LazyConnectionsTable( $this->searchName );
			$p->disableVirtualMode();
			while( ($id = $sqlQ->fetchColumn(0)) && FALSE !== $id ) $p[] = $id;
			$p->enableVirtualMode();
			return $p;
		}
	}
	
	/** Additional method used to determine conventional table name.
	  * @param NULL|string $name A model name used to create a table name from it. If NULL, actual class' name is assumed.
	  * @returns string
	  * @since 0.1
	  */
	protected function tableName($name) {
    if( $this->uniqTableName ) return $this->uniqTableName;
		$modelName = strtolower( $name );
		$lastCharacter = $modelName[strlen($modelName)-1];
		return in_array( $lastCharacter, Array( 's','h' ) )? $modelName . "es" : $modelName . "s";
	}	   
}
?>
