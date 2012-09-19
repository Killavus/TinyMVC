<?php
/** The controller engine class used to provide basic functionality for controllers.
  * @author Killavus
  * @package Killavus
  * @since 0.1
  */
abstract class AbstractController {
   /** Variable used for storing view variables.
     * @returns Array
     * @since 0.1
     */
   protected $viewVariables;
   /** Variable which returns an actual scope of the renderer. In most cases it'll be an array (actual controller,actual method), 
     * but you can override it using setScope function to, for example, redirect the page with content used with actual controller/method.
     * @see AbstractController::setScope
     * @returns Array
     * @since 0.1
     */
   protected $scope;
   
   /** Boolean used for determining whether view data should be preserved or not. Defaults to FALSE.
     * @returns boolean
     * @since 0.1
     */
   protected $saveData;
   
   public function __construct() {
      spl_autoload_register( Array( 'AbstractController', 'autoload' ) );
      $this->viewVariables = Array();
      $this->scope = Array( get_class($this), 'index' );
      $this->saveData = FALSE;
   }
   
   /** Function registered using spl_autoload_register to load all models.
     * @since 0.1
     */
   protected static function autoload( $model ) {
      if( file_exists( MVC_MAIN_DIRECTORY . MVC_MODELS . $model . '.php' ) ) require_once MVC_MAIN_DIRECTORY . MVC_MODELS . $model . '.php';
   }
   
   /** Function which will be called before processing a controller function. Used by the System class. If it'll return something that's not true, rendering won't occur.
     * @since 0.1
     * @param string $method A called method.
     */
   public function preload( $method = NULL ) { return TRUE; }
   /** Function which will be called after processing a controller function. Used by the System class.
     * @since 0.1
     * @param string $method A called method.
     */   
   public function postload( $method = NULL ) { return TRUE; }
   /** Function which will be called before rendering a controller function. Used by the System class. If it'll return something that's not true, rendering won't occur.
     * @since 0.1
     * @param string $method A called method.
     */   
   public function prerender( $method = NULL ) { return TRUE; }
   /** Function which will be called after rendering a controller function. Used by the System class.
     * @since 0.1
     * @param string $method A called method.
     */   
   public function postrender( $method = NULL ) { return TRUE; }
   
   /** Renders a view corresponding to actual method. 
     * @since 0.1
     * @param null|string $controller A controller used. If NULL, an actual is used.
     * @param null|string $method A method used. If NULL, an actual is used.
     * @throws ControllerException When view does not exist.
     */
   public function render( $controller = NULL, $method = NULL ) {
      $controller = $controller? $controller : $this->scope[0];
      $method = $method? $method : $this->scope[1];
      $viewDirectory = MVC_MAIN_DIRECTORY . MVC_VIEWS . strtolower($controller) . '_' . strtolower( $method ) . '.php';
      if( !file_exists( $viewDirectory ) ) throw new ControllerException( 'view does not exist: ' . $viewDirectory );
      if( $this->prerender( $method ) != TRUE ) return;
      
      $var = $this->viewVariables;     
      require_once $viewDirectory;
      $this->postrender( $method );
      if( !$this->saveData ) $this->viewVariables = Array();
   }
   
   /** Renders a static file from static project directory.
     * @since 0.1
     * @param string $file File to be rendered.
     * @throws ControllerException When static page does not exist.
     */
   public function renderStatic( $file ) {
      if( !file_exists( DIRECTORIES_STATIC . $file ) ) throw new ControllerException( 'static page does not exist' );
      if( $this->prerender( $method ) != TRUE ) return;
      $var = $this->viewVariables;
      require_once DIRECTORIES_STATIC . $file;
      $this->postrender( $method );      
      if( !$this->saveData ) $this->viewVariables = Array();
   }
   
   /** Starts catching the rendered data.
     * @since 0.1
     * @returns this
     */
   public function beginCatching() {
      ob_start();
      return $this;
   }
   
   /** Catches the rendered data.
     * @since 0.1
     * @returns string
     */
   public function catchData() {
      $ret = ob_get_contents();
      ob_end_clean();
      return $ret;
   }
   
   /** Sets whether view data should be saved or not after rendering.
     * @since 0.1
     * @param boolean $var If TRUE, data will be saved. FALSE otherwise.
     * @returns this
     */
   public function setSaving( $var = FALSE ) {
      $this->saveData = $var;
      return $this;
   }
   
   /** Sets an actual scope. Useful when redirecting pages one to another.
     * @since 0.1
     * @param string $controller A controller on which scope should be changed.
     * @param string $method A method on which scope should be changed.
     */
   public function setScope( $controller, $method = NULL ) {
      $this->scope = Array( $controller, $method? $method : 'index' );
   }
}
?>