<?php
/** Główna klasa systemu, zbierająca najważniejsze dane i pełniąca rolę routera w strukturze MVC.
  * @author Marcin Grzywaczewski [marcin.grzywaczewski@gmail.com]
  */
require_once 'system/Config.php';
require_once 'system/CustomConfig.php';

function __($file) {
  global $sys;
  return $sys->__($file);
}

class System {
  /** Tworzy nowy obiekt klasy System.
    * @returns this
    */
  public function __construct() {
    spl_autoload_register( Array( get_class($this), 'autoloader' ) );
  }
  
  public static function autoloader( $className ) {
    // Ładujemy kontroler
    if( strpos( $className, "Controller" ) !== FALSE ) {
      list( $fileName ) = explode( 'Controller', $className );
      require_once MVC_MAIN_DIRECTORY . MVC_CONTROLLERS . $fileName . '.php';
    }
  }
  /** Inicjalizuje zmienne globalne wykorzystywane w projekcie i cały system.
		*/
  public function initialise() {
    $globalDirectory = opendir( DIRECTORIES_GLOBALS );
    $arr = Array();
    while( ( $globalClassFile = readdir( $globalDirectory ) ) !== FALSE ) $arr[] = $globalClassFile;
    closedir( $globalDirectory );  
    sort($arr);
    foreach( $arr as $globalClassFile ) {
      require_once DIRECTORIES_GLOBALS . 'Exceptions.php';
      require_once DIRECTORIES_GLOBALS . 'Debug.php';
      $GLOBALS[ 'g_debug' ] = new Debug();
      
      if( $globalClassFile == '.' || $globalClassFile == '..' || $globalClassFile == "Debug.php" || $globalClassFile == 'Exceptions.php' ) continue;
      require_once DIRECTORIES_GLOBALS . $globalClassFile;
      list( $globalClass ) = explode( '.', $globalClassFile );
      if( ltrim( $globalClass, "Singleton" ) == $globalClass ) $GLOBALS[ 'g_' . strtolower( $globalClass ) ] = new $globalClass();
    }
    
    global $g_database;   
    if( DATABASE_USES_UTF8_NAMES ) $g_database->handler()->query( 'SET NAMES utf8;' );
  }
  
  /** Startuje generowanie stron za pomocą kontrolerów. Zwraca TRUE, jeżeli wszystko się udało. W przypadku wystąpienia jakiegokolwiek błędu zwraca false.
    * @throws ModuleException Gdy nie istnieje kontroler, który można by wygenerować, lub akcja jest niedozwolona (np. nie istnieje).
    * @throws AccessException Gdy uprawnienia nie pozwalają dostać się do konkretnej strony.
    * @throws GlobalException Gdy coś poszło bardzo nie tak i błąd jest na poziomie samego systemu. Aktualnie nie jest on rzucany.
    * @returns boolean
    */
  public function start() {
    /** Sanityzacja: */
    $controller = isSet($_GET['controller'])? ucfirst($_GET['controller']) : CONTROLLERS_MAIN;
    $controller = filter_var( $controller, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH );
    $controller = str_replace( '.', '', $controller );
    $method = isSet($_GET['method'])? $_GET['method'] : 'index';   
  
    if( !file_exists( MVC_MAIN_DIRECTORY . MVC_CONTROLLERS . $controller . '.php' ) ) {
      throw new ModuleException( 'failed to find the controller file: ' . MVC_MAIN_DIRECTORY . MVC_CONTROLLERS . ucfirst($controller) . '.php' );
      return FALSE;
		}

    $controller = $controller . 'Controller';    
		$module = new $controller();
    if( !method_exists( $module, $method ) ) {
      throw new ModuleException( 'failed to find the method ' . $method . ' in ' . $controller . '.' );
      return FALSE;
    }

    if( call_user_func_array( Array( &$module, 'preload' ), Array( $method ) ) != TRUE ) return FALSE;
    list( $controller ) = explode( 'Controller', $controller );
    $module->setScope( $controller, $method );
    call_user_func_array( Array( &$module, $method ), $this->parseArguments() );
    call_user_func_array( Array( &$module, 'postload' ), Array( $method ) );
    return TRUE;
  }
  
  /** Generuje statyczną stronę.
    */
  public function _static( $file ) {
    require DIRECTORIES_STATIC . $file;
  }

  /** Generuje szablon.
    */  
  public function _template( $file ) {
    require DIRECTORIES_TEMPLATES . $file;
  }
  
  /** Tworzy absolutny adres do pliku - przydatne w przypadku mod_rewrite.
    */
  public function __( $file, $silent = FALSE ) {
    $ret = '/' . SYSTEM_DIRECTORY . $file;
    if( $silent ) return $ret;
    else echo $ret;
  }
  
  /** Przetwarza, waliduje i przygotowuje jako tablice argumenty przekazane w tablicy $_GET.
    * @returns array
    */
  public function parseArguments() {
    if( !isSet( $_GET['args'] ) ) return Array();
    else return explode( '|', $_GET['args'] );
  }  
}
/* Jeżeli użytkownik ma ciasteczko, w którym przechowuje sesje, zmieniamy aktualną sesję na tą z ciasteczka. */
if( isSet( $_COOKIE[ USER_COOKIE_PREFIX . 'ssid' ] ) ) session_id( $_COOKIE[ USER_COOKIE_PREFIX . 'ssid' ] );
session_start();

$sys = new System;
$sys->initialise();
?>
