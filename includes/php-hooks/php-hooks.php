<?php
/**
 * PHP Hooks Class
 *
 * The PHP Hooks Class is a fork of the WordPress filters hook system rolled in to a class to be ported 
 * into any php based system
 *
 * This class is heavily based on the WordPress plugin API and most (if not all) of the code comes from there.
 * 
 * 
 * @version 0.1.3
 * @copyright 2012 - 2014
 * @author Ohad Raz (email: admin@bainternet.info)
 * @link http://en.bainternet.info
 * 
 * @license GNU General Public LIcense v3.0 - license.txt
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NON-INFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 * @package PHP Hooks
 */
if (!class_exists('Hooks')){
  /**
  * Hooks
  */
  class Hooks
  {
    /**
     * $filters holds list of hooks
     * @access public
     * @since 0.1
     * @var array
     */
    var $filters = array();
    /**
     * $merged_filters
     * @var array
     */
    var $merged_filters = array();
    /**
     * $actions 
     * @var array
     */
    var $actions = array();
    /**
     * $current_filter  holds the name of the current filter
     * @access public
     * @since 0.1
     * @var array
     */
    var $current_filter = array();
    
    /**
     * __construct class constructor
     * @access public
     * @since 0.1
     */
    public function __construct($args = null)
    {
      $this->filters = array();
      $this->merged_filters = array();
      $this->actions = array();
      $this->current_filter = array();
    }

    /**
     * FILTERS
     */

    /**
     * add_filter Hooks a function or method to a specific filter action.
     * @access public
     * @since 0.1
     * @param string $tag The name of the filter to hook the $function_to_add to.
     * @param callback $function_to_add The name of the function to be called when the filter is applied.
     * @param int $priority optional. Used to specify the order in which the functions associated with a particular action are executed (default: 10). Lower numbers correspond with earlier execution, and functions with the same priority are executed in the order in which they were added to the action.
     * @param int $accepted_args optional. The number of arguments the function accept (default 1).
     * @return boolean true
     */
    public function add_filter($tag, $function_to_add, $priority = 10, $accepted_args = 1) {
      $idx =  $this->_filter_build_unique_id($tag, $function_to_add, $priority);
      $this->filters[$tag][$priority][$idx] = array('function' => $function_to_add, 'accepted_args' => $accepted_args);
      unset( $this->merged_filters[ $tag ] );
      return true;
    }
    /**
     * remove_filter Removes a function from a specified filter hook.
     * @access public
     * @since 0.1
     * @param string $tag The filter hook to which the function to be removed is hooked.
     * @param callback $function_to_remove The name of the function which should be removed.
     * @param int $priority optional. The priority of the function (default: 10).
     * @param int $accepted_args optional. The number of arguments the function accepts (default: 1).
     * @return boolean Whether the function existed before it was removed.
     */
    public function remove_filter( $tag, $function_to_remove, $priority = 10 ) {
      $function_to_remove = $this->_filter_build_unique_id($tag, $function_to_remove, $priority);

      $r = isset($this->filters[$tag][$priority][$function_to_remove]);

      if ( true === $r) {
        unset($this->filters[$tag][$priority][$function_to_remove]);
        if ( empty($this->filters[$tag][$priority]) )
          unset($this->filters[$tag][$priority]);
        unset($this->merged_filters[$tag]);
      }
      return $r;
    }
    /**
     * remove_all_filters Remove all of the hooks from a filter.
     * @access public
     * @since 0.1
     * @param string $tag The filter to remove hooks from.
     * @param int $priority The priority number to remove.
     * @return bool True when finished.
     */
    public function remove_all_filters($tag, $priority = false) {
      if( isset($this->filters[$tag]) ) {
        if( false !== $priority && isset($this->filters[$tag][$priority]) )
          unset($this->filters[$tag][$priority]);
        else
          unset($this->filters[$tag]);
      }

      if( isset($this->merged_filters[$tag]) )
        unset($this->merged_filters[$tag]);

      return true;
    }
    /**
     * has_filter  Check if any filter has been registered for a hook.
     * @access public
     * @since 0.1
     * @param string $tag The name of the filter hook.
     * @param callback $function_to_check optional.
     * @return mixed If $function_to_check is omitted, returns boolean for whether the hook has anything registered.
     *   When checking a specific function, the priority of that hook is returned, or false if the function is not attached.
     *   When using the $function_to_check argument, this function may return a non-boolean value that evaluates to false
     *   (e.g.) 0, so use the === operator for testing the return value.
     */
    public function has_filter($tag, $function_to_check = false) {
      $has = !empty($this->filters[$tag]);
      if ( false === $function_to_check || false == $has )
        return $has;

      if ( !$idx = $this->_filter_build_unique_id($tag, $function_to_check, false) )
        return false;

      foreach ( (array) array_keys($this->filters[$tag]) as $priority ) {
        if ( isset($this->filters[$tag][$priority][$idx]) )
          return $priority;
      }
      return false;
    }
    /**
     * apply_filters Call the functions added to a filter hook.
     * @access public
     * @since 0.1
     * @param string $tag The name of the filter hook.
     * @param mixed $value The value on which the filters hooked to <tt>$tag</tt> are applied on.
     * @param mixed $var,... Additional variables passed to the functions hooked to <tt>$tag</tt>.
     * @return mixed The filtered value after all hooked functions are applied to it.
     */
    public function apply_filters($tag, $value) {
      $args = array();
      // Do 'all' actions first
      if ( isset($this->filters['all']) ) {
        $this->current_filter[] = $tag;
        $args = func_get_args();
        $this->_call_all_hook($args);
      }

      if ( !isset($this->filters[$tag]) ) {
        if ( isset($this->filters['all']) )
          array_pop($this->current_filter);
        return $value;
      }

      if ( !isset($this->filters['all']) )
        $this->current_filter[] = $tag;

      // Sort
      if ( !isset( $this->merged_filters[ $tag ] ) ) {
        ksort($this->filters[$tag]);
        $this->merged_filters[ $tag ] = true;
      }

      reset( $this->filters[ $tag ] );

      if ( empty($args) )
        $args = func_get_args();

      do {
        foreach( (array) current($this->filters[$tag]) as $the_ )
          if ( !is_null($the_['function']) ){
            $args[1] = $value;
            $value = call_user_func_array($the_['function'], array_slice($args, 1, (int) $the_['accepted_args']));
          }

      } while ( next($this->filters[$tag]) !== false );

      array_pop( $this->current_filter );

      return $value;
    }
    /**
     * apply_filters_ref_array Execute functions hooked on a specific filter hook, specifying arguments in an array.
     * @access public
     * @since 0.1
     * @param string $tag The name of the filter hook.
     * @param array $args The arguments supplied to the functions hooked to <tt>$tag</tt>
     * @return mixed The filtered value after all hooked functions are applied to it.
     */
    public function apply_filters_ref_array($tag, $args) {
      // Do 'all' actions first
      if ( isset($this->filters['all']) ) {
        $this->current_filter[] = $tag;
        $all_args = func_get_args();
        $this->_call_all_hook($all_args);
      }

      if ( !isset($this->filters[$tag]) ) {
        if ( isset($this->filters['all']) )
          array_pop($this->current_filter);
        return $args[0];
      }

      if ( !isset($this->filters['all']) )
        $this->current_filter[] = $tag;

      // Sort
      if ( !isset( $this->merged_filters[ $tag ] ) ) {
        ksort($this->filters[$tag]);
        $this->merged_filters[ $tag ] = true;
      }

      reset( $this->filters[ $tag ] );

      do {
        foreach( (array) current($this->filters[$tag]) as $the_ )
          if ( !is_null($the_['function']) )
            $args[0] = call_user_func_array($the_['function'], array_slice($args, 0, (int) $the_['accepted_args']));

      } while ( next($this->filters[$tag]) !== false );

      array_pop( $this->current_filter );

      return $args[0];
    }

    /**
     * ACTIONS
     */

    /**
     * add_action Hooks a function on to a specific action.
     * @access public
     * @since 0.1
     * @param string $tag The name of the action to which the $function_to_add is hooked.
     * @param callback $function_to_add The name of the function you wish to be called.
     * @param int $priority optional. Used to specify the order in which the functions associated with a particular action are executed (default: 10). Lower numbers correspond with earlier execution, and functions with the same priority are executed in the order in which they were added to the action.
     * @param int $accepted_args optional. The number of arguments the function accept (default 1).
     */
    public function add_action($tag, $function_to_add, $priority = 10, $accepted_args = 1) {
      return $this->add_filter($tag, $function_to_add, $priority, $accepted_args);
    }
    /**
     * has_action Check if any action has been registered for a hook.
     * @access public
     * @since 0.1
     * @param string $tag The name of the action hook.
     * @param callback $function_to_check optional.
     * @return mixed If $function_to_check is omitted, returns boolean for whether the hook has anything registered.
     *   When checking a specific function, the priority of that hook is returned, or false if the function is not attached.
     *   When using the $function_to_check argument, this function may return a non-boolean value that evaluates to false
     *   (e.g.) 0, so use the === operator for testing the return value.
     */
    public function has_action($tag, $function_to_check = false) {
      return $this->has_filter($tag, $function_to_check);
    }
    /**
     * remove_action Removes a function from a specified action hook.
     * @access public
     * @since 0.1
     * @param string $tag The action hook to which the function to be removed is hooked.
     * @param callback $function_to_remove The name of the function which should be removed.
     * @param int $priority optional The priority of the function (default: 10).
     * @return boolean Whether the function is removed.
     */
    public function remove_action( $tag, $function_to_remove, $priority = 10 ) {
      return $this->remove_filter( $tag, $function_to_remove, $priority );
    }
    /**
     * remove_all_actions Remove all of the hooks from an action.
     * @access public
     * @since 0.1
     * @param string $tag The action to remove hooks from.
     * @param int $priority The priority number to remove them from.
     * @return bool True when finished.
     */
    public function remove_all_actions($tag, $priority = false) {
      return $this->remove_all_filters($tag, $priority);
    }
    /**
     * do_action Execute functions hooked on a specific action hook.
     * @access public
     * @since 0.1
     * @param string $tag The name of the action to be executed.
     * @param mixed $arg,... Optional additional arguments which are passed on to the functions hooked to the action.
     * @return null Will return null if $tag does not exist in $filter array
     */
    public function do_action($tag, $arg = '') {

      if ( ! isset($this->actions) )
        $this->actions = array();

      if ( ! isset($this->actions[$tag]) )
        $this->actions[$tag] = 1;
      else
        ++$this->actions[$tag];

      // Do 'all' actions first
      if ( isset($this->filters['all']) ) {
        $this->current_filter[] = $tag;
        $all_args = func_get_args();
        $this->_call_all_hook($all_args);
      }

      if ( !isset($this->filters[$tag]) ) {
        if ( isset($this->filters['all']) )
          array_pop($this->current_filter);
        return;
      }

      if ( !isset($this->filters['all']) )
        $this->current_filter[] = $tag;

      $args = array();
      if ( is_array($arg) && 1 == count($arg) && isset($arg[0]) && is_object($arg[0]) ) // array(&$this)
        $args[] =& $arg[0];
      else
        $args[] = $arg;
      for ( $a = 2; $a < func_num_args(); $a++ )
        $args[] = func_get_arg($a);

      // Sort
      if ( !isset( $this->merged_filters[ $tag ] ) ) {
        ksort($this->filters[$tag]);
        $this->merged_filters[ $tag ] = true;
      }

      reset( $this->filters[ $tag ] );

      do {
        foreach ( (array) current($this->filters[$tag]) as $the_ )
          if ( !is_null($the_['function']) )
            call_user_func_array($the_['function'], array_slice($args, 0, (int) $the_['accepted_args']));

      } while ( next($this->filters[$tag]) !== false );

      array_pop($this->current_filter);
    }
    /**
     * do_action_ref_array Execute functions hooked on a specific action hook, specifying arguments in an array.
     * @access public
     * @since 0.1
     * @param string $tag The name of the action to be executed.
     * @param array $args The arguments supplied to the functions hooked to <tt>$tag</tt>
     * @return null Will return null if $tag does not exist in $filter array
     */
    public function do_action_ref_array($tag, $args) {
      
      if ( ! isset($this->actions) )
        $this->actions = array();

      if ( ! isset($this->actions[$tag]) )
        $this->actions[$tag] = 1;
      else
        ++$this->actions[$tag];

      // Do 'all' actions first
      if ( isset($this->filters['all']) ) {
        $this->current_filter[] = $tag;
        $all_args = func_get_args();
        $this->_call_all_hook($all_args);
      }

      if ( !isset($this->filters[$tag]) ) {
        if ( isset($this->filters['all']) )
          array_pop($this->current_filter);
        return;
      }

      if ( !isset($this->filters['all']) )
        $this->current_filter[] = $tag;

      // Sort
      if ( !isset( $merged_filters[ $tag ] ) ) {
        ksort($this->filters[$tag]);
        $merged_filters[ $tag ] = true;
      }

      reset( $this->filters[ $tag ] );

      do {
        foreach( (array) current($this->filters[$tag]) as $the_ )
          if ( !is_null($the_['function']) )
            call_user_func_array($the_['function'], array_slice($args, 0, (int) $the_['accepted_args']));

      } while ( next($this->filters[$tag]) !== false );

      array_pop($this->current_filter);
    }
    /**
     * did_action Retrieve the number of times an action is fired.
     * @access public
     * @since 0.1
     * @param string $tag The name of the action hook.
      * @return int The number of times action hook <tt>$tag</tt> is fired
     */
    public function did_action($tag) {

      if ( ! isset( $this->actions ) || ! isset( $this->actions[$tag] ) )
        return 0;

      return $this->actions[$tag];
    }

    /**
     * HELPERS
     */

    /**
     * current_filter Retrieve the name of the current filter or action.
     * @access public
     * @since 0.1
     * @return string Hook name of the current filter or action.
     */
    public function current_filter() {
      return end( $this->current_filter );
    }
    /**
     * Retrieve the name of the current action.
     *
     * @since 0.1.2
     *
     * @uses current_filter()
     *
     * @return string Hook name of the current action.
     */
    function current_action() {
      return $this->current_filter();
    }
    
    /**
     * Retrieve the name of a filter currently being processed.
     *
     * The function current_filter() only returns the most recent filter or action
     * being executed. did_action() returns true once the action is initially
     * processed. This function allows detection for any filter currently being
     * executed (despite not being the most recent filter to fire, in the case of
     * hooks called from hook callbacks) to be verified.
     *
     * @since 0.1.2
     *
     * @see current_filter()
     * @see did_action()
     * @global array $wp_current_filter Current filter.
     *
     * @param null|string $filter Optional. Filter to check. Defaults to null, which
     *                            checks if any filter is currently being run.
     * @return bool Whether the filter is currently in the stack
     */
    function doing_filter( $filter = null ) {
      if ( null === $filter ) {
        return ! empty( $this->current_filter );
      } 
      return in_array( $filter, $this->current_filter );
    }
    
    /**
     * Retrieve the name of an action currently being processed.
     *
     * @since 0.1.2
     *
     * @uses doing_filter()
     *
     * @param string|null $action Optional. Action to check. Defaults to null, which checks
     *                            if any action is currently being run.
     * @return bool Whether the action is currently in the stack.
     */
    function doing_action( $action = null ) {
      return $this->doing_filter( $action );
    }
    
    /**
     * _filter_build_unique_id Build Unique ID for storage and retrieval.
     * @param string $tag Used in counting how many hooks were applied
     * @param callback $function Used for creating unique id
     * @param int|bool $priority Used in counting how many hooks were applied. If === false and $function is an object reference, we return the unique id only if it already has one, false otherwise.
     * @return string|bool Unique ID for usage as array key or false if $priority === false and $function is an object reference, and it does not already have a unique id.
     */
    private function _filter_build_unique_id($tag, $function, $priority) {
      static $filter_id_count = 0;

      if ( is_string($function) )
        return $function;

      if ( is_object($function) ) {
        // Closures are currently implemented as objects
        $function = array( $function, '' );
      } else {
        $function = (array) $function;
      }

      if (is_object($function[0]) ) {
        // Object Class Calling
        if ( function_exists('spl_object_hash') ) {
          return spl_object_hash($function[0]) . $function[1];
        } else {
          $obj_idx = get_class($function[0]).$function[1];
          if ( !isset($function[0]->filter_id) ) {
            if ( false === $priority )
              return false;
            $obj_idx .= isset($this->filters[$tag][$priority]) ? count((array)$this->filters[$tag][$priority]) : $filter_id_count;
            $function[0]->filter_id = $filter_id_count;
            ++$filter_id_count;
          } else {
            $obj_idx .= $function[0]->filter_id;
          }

          return $obj_idx;
        }
      } else if ( is_string($function[0]) ) {
        // Static Calling
        return $function[0].$function[1];
      }
    }

    /**
     * __call_all_hook
     * @access public
     * @since 0.1
     * @param  (array) $args [description]
     */
    public function __call_all_hook($args) {
      reset( $this->filters['all'] );
      do {
        foreach( (array) current($this->filters['all']) as $the_ )
          if ( !is_null($the_['function']) )
            call_user_func_array($the_['function'], $args);

      } while ( next($this->filters['all']) !== false );
    }
  }//end class
}//end if
global $hooks;
$hooks = new Hooks();
$hooks->do_action('After_Hooks_Setup',$hooks);