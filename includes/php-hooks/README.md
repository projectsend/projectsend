PHP-Hooks
=========

The PHP Hooks Class is a fork of the WordPress filters hook system rolled in to a class to be ported into any php based system  
*  This class is heavily based on the WordPress plugin API and most (if not all) of the code comes from there.


Head Over to [http://bainternet.github.io/PHP-Hooks/][3] For more info

----------

How to Use?
=====

Simple, Include the class file in your application bootstrap (setup/load/configuration or whatever you call it) and start hooking your filter and action hooks using the global `$hooks`. Ex:

```PHP
include_once('php-hooks.php');
global $hooks;
$hooks->add_action('header_action','echo_this_in_header');

function echo_this_in_header(){
   echo 'this came from a hooked function';
}
```

then all that is left for you is to call the hooked function when you want anywhere in your aplication, EX:

```PHP
echo '<div id="extra_header">';
global $hooks;
$hooks->do_action('header_action');
echo '</div>';
```


and you output will be: 
```HTML
<div id="extra_header">this came from a hooked function</div>
```

Methods
=======
**ACTIONS:**

**add_action** Hooks a function on to a specific action.

     - @access public
     - @since 0.1
     - @param string $tag The name of the action to which the $function_to_add is hooked.
     - @param callback $function_to_add The name of the function you wish to be called.
     - @param int $priority optional. Used to specify the order in which the functions associated with a particular action are executed (default: 10). Lower numbers correspond with earlier execution, and functions with the same priority are executed in the order in which they were added to the action.
     - @param int $accepted_args optional. The number of arguments the function accept (default 1).

**do_action** Execute functions hooked on a specific action hook.

     - @access public
     - @since 0.1
     - @param string $tag The name of the action to be executed.
     - @param mixed $arg,... Optional additional arguments which are passed on to the functions hooked to the action.
     - @return null Will return null if $tag does not exist

**remove_action** Removes a function from a specified action hook.


     - @access public
     - @since 0.1
     - @param string $tag The action hook to which the function to be removed is hooked.
     - @param callback $function_to_remove The name of the function which should be removed.
     - @param int $priority optional The priority of the function (default: 10).
     - @return boolean Whether the function is removed.

**has_action** Check if any action has been registered for a hook.

     -  @access public
     -  @since 0.1
     -  @param string $tag The name of the action hook.
     -  @param callback $function_to_check optional.
     -  @return mixed If $function_to_check is omitted, returns boolean for whether the hook has anything registered.
      When checking a specific function, the priority of that hook is returned, or false if the function is not attached.
      When using the $function_to_check argument, this function may return a non-boolean value that evaluates to false (e.g.) 0, so use the === operator for testing the return value.


**did_action**  Retrieve the number of times an action is fired.

     -  @access public
     -  @since 0.1
     -  @param string $tag The name of the action hook.
     -  @return int The number of times action hook <tt>$tag</tt> is fired

**FILTERS:**

**add_filter** Hooks a function or method to a specific filter action.

     - @access public
     -  @since 0.1
     -  @param string $tag The name of the filter to hook the $function_to_add to.
     -  @param callback $function_to_add The name of the function to be called when the filter is applied.
     -  @param int $priority optional. Used to specify the order in which the functions associated with a particular action are executed (default: 10). Lower numbers correspond with earlier execution, and functions with the same priority are executed in the order in which they were added to the action.
     -  @param int $accepted_args optional. The number of arguments the function accept (default 1).
     -  @return boolean true

**remove_filter** Removes a function from a specified filter hook.

     -  @access public
     -  @since 0.1
     -  @param string $tag The filter hook to which the function to be removed is hooked.
     -  @param callback $function_to_remove The name of the function which should be removed.
     -  @param int $priority optional. The priority of the function (default: 10).
     -  @param int $accepted_args optional. The number of arguments the function accepts (default: 1).
     -  @return boolean Whether the function existed before it was removed.


**has_filter** Check if any filter has been registered for a hook.

     -   @access public
     -   @since 0.1
     -   @param string $tag The name of the filter hook.
     -   @param callback $function_to_check optional.
     -   @return mixed If $function_to_check is omitted, returns boolean for whether the hook has anything registered.
       When checking a specific function, the priority of that hook is  returned, or false if the function is not attached.
       When using the $function_to_check argument, this function may return a non-boolean value that evaluates to false (e.g.) 0, so use the === operator for testing the return value.

**apply_filters** Call the functions added to a filter hook.

     -  @access public
     -  @since 0.1
     -  @param string $tag The name of the filter hook.
     -  @param mixed $value The value on which the filters hooked to <tt>$tag</tt> are applied on.
     -  @param mixed $var,... Additional variables passed to the functions hooked to <tt>$tag</tt>.
     -  @return mixed The filtered value after all hooked functions are applied to it.

There are a few more methods but these are the main Ones you'll use :).

Download
========
You can download this project in either [zip][1] or [tar][2] formats

You can also clone the project with Git by running:

    $ git clone git://github.com/bainternet/PHP-Hooks.git

License
=======

Since this class is derived from the WordPress Plugin API so are the license and they are GPL http://www.gnu.org/licenses/gpl.html

  [1]: https://github.com/bainternet/PHP-Hooks/zipball/master
  [2]: https://github.com/bainternet/PHP-Hooks/tarball/master
  [3]: http://bainternet.github.com/PHP-Hooks/
[![Analytics](https://ga-beacon.appspot.com/UA-50573135-5/PHP-Hooks/main)](https://github.com/bainternet/PHP-Hooks)
