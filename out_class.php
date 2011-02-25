<?php

/**
 *  This is a debug class mostly, designed to ease my echo pains and print things
 *  out formatted without much work on my part       
 *
 *  
 *  EXAMPLE (using this class):
 *    out::e($variable); // that's it
 *    out::h(1); // prints 'here 1'
 *    out::b('title',5); // prints 5 lines of = with title in the middle, just try it and you'll understand 
 *    out::i($variable); // prints out information about the $variable
 *    out::t(); // print stack trace from wherever you are
 *    out::c($variable); // print out the characters of the varialbe using octal dump
 *    out::m($array,'time'); // print time($array[$index]) on each of the values in $array   
 *    
 *    use any of the f* functions (eg, fe()) to do the same thing as their countarparts (eg, fe() is identical
 *    to e()) but put the output into a file instead of to the screen
 *  
 *  IDEAS:
 *    1 - out::t() could parse the file and get the 3-5 before and after lines for each call to see the function
 *        call in context, might be cool (symfony does something similar)
 *    2 - when going through an array, I think an object should do something like:
 *        1 - if it has a __tostring, use that and print it out.
 *        2 - if no tostring, try reflection like out::i() and just get the properties, don't print out objects though,
 *            just do CLASSNAME instance, or recurse maybe 3 deep or something
 *        3 - just print CLASSNAME instance if 1 and 2 fail       
 *    3 - maybe I can do something with http://php.net/manual/en/function.debug-zval-dump.php
 *    4 - for memory, I could try passing in an object and then taking the memory and then unsetting the object
 *        and seeing how much memory is freed  
 * 
 *  KNOWN BUGS:
 *    1 - 2 function calls on the same line (eg, out::e($one); out::e($two)) the second
 *         function will get $one as its var name. This could probably be solved by having a static map that
 *         holds how many times out has been called from a given file:line combination, it would increment everytime
 *         it is called, so then you could split the line on the semi-colon and only look at the count index of the split
 *         array., so if you hade something like: out::e($one); out::e($two); then when you got to out::e($two); the 
 *         count_map would already have 1 for out::e($one); so you could split on the semi-colon and then just look
 *         at index 1 of the split array, then increment the count for the file:line in count_map 
 *    2 - index highlighting on arrays doesn't work if there are a ton of indexes to be highlighted)   
 */         
class out {
  
  /**
   *  used internally to tell {@link put()} to output to std out
   */     
  const OUT_SCREEN = 0;
  
  /**
   *  used internally to tell {@link put()} to output to the $OUT_TO specified file
   */
  const OUT_FILE = 1;
  
  /**
   *  used internally to tell {@link put()} to return the generated output string
   */
  const OUT_STR = 2;
  
  /**
   *  hold the default out type
   *  
   *  can be any value of OUT_*
   *  @var  integer         
   */     
  private static $OUT_DEFAULT = -1;
  
  /**
   *  hold the default way to treate objects when printing them as arrays
   *  
   *  @var  boolean
   */     
  private static $PRINT_OBJECTS = false;
  
  /**
   *  if you use the @link *f() functions then the name of the file outputted
   *  will be this value, by default this is set to CURRENT_WORKING_DIR/out.txt  
   *  @var  string
   */        
  private static $OUT_TO = 'out.txt';
  
  /**
   *  hold info for the {@link p()} calls 
   *     
   *  @var  array
   */
  private static $PROFILE_STACK = array();
  
  /**
   *  switched to false in {@link put()} when a f*() out is performed
   *     
   *  @var  boolean
   */
  private static $first_file_call = true;

  /** php 5.3. feature...
  static function __callstatic($func,$args){
    new d($func);
    new d($args);
  }//method
  **/

  /**
   *  remove $OUT_TO file
   *
   *  deletes the out file, this is handy if you want to check output on every test
   *  so you don't have to manually delete the OUT_FILE...
   *  
   *  @return boolean               
   */
  static function fr(){ return is_file(self::$OUT_TO) ? unlink(self::$OUT_TO) : true; }//method

  /**
   *  set where the *f should write to, currently this is a path
   *  
   *  later this can be expanded to things like email addresses or urls
   *  
   *  @param  string  $name the name of the file where output should be written
   */
  static function to($name){
  
    // canary...
    if(empty($name)){ return; }//if
  
    $path = dirname($name);
  
    // make sure the path is a valid directory, create it if it isn't...
    if(!is_dir($path)){
      if(!mkdir($path,0755,true)){
        throw new Exception(sprintf('%s - mkdir("%s") failed',__METHOD__,$path));
      }//if
    }//if
    
    if(!is_writable($path)){
      throw new Exception(sprintf('%s - "%s" is not writeable',__METHOD__,$path));
    }//if
  
    self::$OUT_TO = $name;
  
  }//method

  /**
   *  similar to {@link e()} but returns the string instead
   */
  static function se(){
    $func_arg_list = func_get_args();
    return self::put(self::eHandle(__METHOD__,$func_arg_list),self::OUT_STR);
  }//method

  /**
   *  similar to {@link e()} but outputs to a file instead
   *  
   *  outputs to a file instead of stdout, the out.txt file will be in the current working directory   
   *  usually whatever directory the php file that is calling out is in.
   */
  static function fe(){
    $func_arg_list = func_get_args();
    return self::put(self::eHandle(__METHOD__,$func_arg_list),self::OUT_FILE);
  }//method

  /**
   *  print anything to the screen, e is a catchall for everything
   *  
   *  to use this function: $var = 'happy'; out::e($var);
   *  
   *  @param  mixed $args,... 1 or more variables that should be printed out
   */
  static function e(){
    $func_arg_list = func_get_args();
    return self::put(self::eHandle(__METHOD__,$func_arg_list),self::OUT_SCREEN);
  }//method
  
  /**
   *  handles the e* calls
   *  
   *  @param  string  $method the externally called method
   *  @param  array $func_arg_list  the args passed into $method
   *  @return out_call   
   */
  private static function eHandle($method,$func_arg_list){
    return self::getCall($method,$func_arg_list);
  }//method
  
  /**
   *  similar to {@link x()} but outputs to a file instead
   */
  static function fx(){
    self::put(self::xHandle(__METHOD__),self::OUT_FILE);
    exit();
  }//method

  /**
   *  calling this method exits the program but lets the user know where it exited
   */
  static function x(){
    self::put(self::xHandle(__METHOD__),self::OUT_SCREEN);
    exit();
  }//method
  
  /**
   *  handles the x* calls
   *  
   *  @param  string  $method the externally called method
   *  @return out_call   
   */
  private static function xHandle($method){
  
    $call_handler = self::getCall($method);
    
    $arg_handler = new out_arg('','Exit Called');
    $arg_handler->type(out_arg::TYPE_STRING_LITERAL);
    $call_handler->set($arg_handler);
    return $call_handler;
    
  }//method
  
  /**
   *  similar to {@link b()} but returns the string
   */        
  static function sb(){
    $func_arg_list = func_get_args();
    return self::put(self::bHandle(__METHOD__,$func_arg_list),self::OUT_STR);
  }//method
  
  /**
   *  similar to {@link b()} but outputs to a file instead
   */        
  static function fb(){
    $func_arg_list = func_get_args();
    return self::put(self::bHandle(__METHOD__,$func_arg_list),self::OUT_FILE);
  }//method
  
  /**
   *  prints a break/divider of =
   *  
   *  @param  mixed $args,... possible values:
   *                            - 1 arg = either a title or how many lines you want (if int val)
   *                            - 2args = $title,$lines               
   */
  static function b(){
    $func_arg_list = func_get_args();
    return self::put(self::bHandle(__METHOD__,$func_arg_list),self::OUT_SCREEN);
  }//method
  
  /**
   *  handles the b* calls
   *  
   *  @param  string  $method the externally called method
   *  @param  array $func_arg_list  the args passed into $method
   *  @return out_call   
   */
  private static function bHandle($method,$func_arg_list){
  
    $call_handler = self::getCall($method,$func_arg_list);
    $title = '';
    $lines = 1;
    if(isset($func_arg_list[1])){
      $title = $func_arg_list[0];
      $lines = $func_arg_list[1];
    }else{
    
      if(isset($func_arg_list[0])){
      
        if(is_int($func_arg_list[0])){
          $lines = $func_arg_list[0];
        }else{
          $title = $func_arg_list[0];
        }//if/else
        
      }//if
    
    }//if/else
    
    $arg_handler = new out_arg($lines,$title);
    $arg_handler->type(out_arg::TYPE_BREAK);
    $call_handler->set($arg_handler);
    
    return $call_handler;
  
  }//method
  
  /**
   *  similar to {@link i()} but returns string
   */
  static function si(){
    $func_arg_list = func_get_args();
    return self::put(self::iHandle(__METHOD__,$func_arg_list),self::OUT_STR);
  }//method
  
  /**
   *  similar to {@link i()} but outputs to a file instead
   */
  static function fi(){
    $func_arg_list = func_get_args();
    return self::put(self::iHandle(__METHOD__,$func_arg_list),self::OUT_FILE);
  }//method

  /**
   *  print info on the passed in $args
   *  
   *  to use this function: $var = 'happy'; out::i($var);
   *  
   *  @param  mixed $args,... 1 or more variables that should be printed out
   */
  static function i(){
    $func_arg_list = func_get_args();
    return self::put(self::iHandle(__METHOD__,$func_arg_list),self::OUT_SCREEN);
  }//method
  
  /**
   *  handles the i* calls
   *  
   *  @param  string  $method the externally called method
   *  @param  array $func_arg_list  the args passed into $method
   *  @return out_call   
   */
  private static function iHandle($method,$func_arg_list){
  
    $call_handler = self::getCall($method,$func_arg_list);
    
    // we only want info on the object printed out...
    $config = $call_handler->config();
    $config->outInfo(true);
    $call_handler->config($config);
    return $call_handler;
    
  }//method
  
  /**
   *  similar to {@link c()} but returns string
   */
  static function fc(){
    $func_arg_list = func_get_args();
    return self::put(self::cHandle(__METHOD__,$func_arg_list),self::OUT_STR);
  }//method
  
  /**
   *  similar to {@link c()} but outputs to a file instead
   */
  static function sc(){
    $func_arg_list = func_get_args();
    return self::put(self::cHandle(__METHOD__,$func_arg_list),self::OUT_FILE);
  }//method

  /**
   *  get character information of the passed in string args
   *  
   *  to use this function: $var = 'happy'; out::c($var);
   *  
   *  @param  mixed $args,... 1 or more variables that should be printed out
   */
  static function c(){
    $func_arg_list = func_get_args();
    return self::put(self::cHandle(__METHOD__,$func_arg_list),self::OUT_SCREEN);
  }//method
  
  /**
   *  handles the c* calls
   *  
   *  @param  string  $method the externally called method
   *  @param  array $func_arg_list  the args passed into $method
   *  @return out_call   
   */
  private static function cHandle($method,$func_arg_list){
  
    $call_handler = self::getCall($method,$func_arg_list);
    
    // we only want info on the object printed out...
    $config = $call_handler->config();
    $config->outChar(true);
    $call_handler->config($config);
    return $call_handler;
    
  }//method
  
  /**
   *  similar to {@link m()} but returns string
   */
  static function sm(){
    $func_arg_list = func_get_args();
    return self::put(self::mHandle(__METHOD__,$func_arg_list),self::OUT_STR);
  }//method
  
  /**
   *  similar to {@link m()} but outputs to a file instead
   */
  static function fm(){
    $func_arg_list = func_get_args();
    return self::put(self::mHandle(__METHOD__,$func_arg_list),self::OUT_FILE);
  }//method

  /**
   *  print out the memory used
   *  
   *  @since  12-07-09
   */ 
  static function m(){
    $func_arg_list = func_get_args();
    return self::put(self::mHandle(__METHOD__,$func_arg_list),self::OUT_SCREEN);
  }//method
  
  /**
   *  handles the mem* calls
   *  
   *  @param  string  $method the externally called method
   *  @param  array $func_arg_list  the args passed into $method
   *  @return out_call   
   */
  private static function mHandle($method,$func_arg_list = array()){
    
    $call_handler = self::getCall($method,$func_arg_list);
    
    if(empty($func_arg_list)){
    
      $format_handler = new out_format($call_handler->config());
      $str = $format_handler->bytes(memory_get_usage(true));
    
      $real_mem_title = $format_handler->wrap('b','Real Memory');
      $real_mem_stats = $format_handler->bytes(memory_get_usage(true));
    
      $malloc_mem_title = $format_handler->wrap('b','Malloc Memory');
      $malloc_mem_stats = $format_handler->bytes(memory_get_usage());
      
      $peak_real_mem_title = $peak_malloc_mem_title = '';
      $peak_real_mem_stats = $peak_malloc_mem_stats = 'Only supported on php >=5.2';
      if(function_exists('memory_get_peak_usage')){
        
        $peak_real_mem_title = $format_handler->wrap('b','Peak Real Memory');
        $peak_real_mem_stats = $format_handler->bytes(memory_get_peak_usage());
        
        $peak_malloc_mem_title = $format_handler->wrap('b','Peak Malloc Memory');
        $peak_malloc_mem_stats = $format_handler->bytes(memory_get_peak_usage(true));
        
      }//if
    
      $arg_val = sprintf("%s\t\t%s\r\n%s\t\t%s\r\n%s\t%s\r\n%s\t%s",
        $real_mem_title,
        $real_mem_stats,
        $malloc_mem_title,
        $malloc_mem_stats,
        $peak_real_mem_title,
        $peak_real_mem_stats,
        $peak_malloc_mem_title,
        $peak_malloc_mem_stats
      );
    
      $arg_handler = new out_arg('',$arg_val);
      $arg_handler->type(out_arg::TYPE_STRING_GENERATED);
      $call_handler->set($arg_handler);
    
    }else{
    
      $config = $call_handler->config();
      $config->outMem(true);
      $call_handler->config($config);
    
    }//if/else
    
    return $call_handler;
    
  }//method
  
  /**
   *  similar to {@link t()} but returns string
   *  @param  integer $history  how many lines back you want to get back   
   */
  static function st($history = 0){
    return self::put(self::tHandle(__METHOD__,$history),self::OUT_STR);
  }//method
  
  /**
   *  similar to {@link t()} but outputs to a file instead
   *  @param  integer $history  how many lines back you want to get back   
   */
  static function ft($history = 0){
    return self::put(self::tHandle(__METHOD__,$history),self::OUT_FILE);
  }//method

  /**
   *  print out the call backtrace history
   *  
   *  @since  4-10-09
   *  @param  integer $history  how many lines back you want to get back   
   */ 
  static function t($history = 0){
    return self::put(self::tHandle(__METHOD__,$history),self::OUT_SCREEN);
  }//method
  
  /**
   *  handles the t* calls
   *  
   *  @param  string  $method the externally called method
   *  @param  integer $history  how many lines back you want to get back
   *  @return out_call   
   */
  private static function tHandle($method,$history = 0){
    
    $call_handler = self::getCall($method);
    
    $arg_handler = new out_arg('',$call_handler->outTrace($history));
    $arg_handler->type(out_arg::TYPE_STRING_GENERATED);
    $call_handler->set($arg_handler);
    return $call_handler;
    
  }//method
  
  /**
   *  similar to {@link m()} but returns string
   */
  static function sf(){
    $func_arg_list = func_get_args();
    return self::put(self::fHandle(__METHOD__,$func_arg_list),self::OUT_STR);
  }//method
  
  /**
   *  similar to {@link F()} but outputs to a file instead
   */
  static function ff(){
    $func_arg_list = func_get_args();
    return self::put(self::fHandle(__METHOD__,$func_arg_list),self::OUT_FILE);
  }//method

  /**
   *  given an array of objects, print out the output of the functions given      
   *  
   *  @since  11-9-09
   *  @param  mixed $arg,...  first passed in argument is an array of objects, every other passed
   *                          in argument is the name of a method of the objects
   */ 
  static function f(){
    $func_arg_list = func_get_args();
    return self::put(self::fHandle(__METHOD__,$func_arg_list),self::OUT_SCREEN);
  }//method
  
  /**
   *  handles the m* calls
   *  
   *  if you have a list of values, then you can use this function to run the same function on
   *  all the values (similar to array_map). If your values are objects, then you can do stuff
   *  like: out::m($list_of_obj,'->getOne()->isvalid()'). lets, say you want to get see what
   *  the trim output of your list of strings, then do: out::m($list_of_str,'trim');         
   *      
   *  @todo allow the method names to be an array were the first index is the name and
   *        the second -> N is the arguments to pass into the method (eg, array('method',$arg1,$arg2,...))   
   *      
   *  @param  string  $method the externally called method
   *  @param  array $func_arg_list  the args passed into $method
   *  @return out_call   
   */
  private static function fHandle($method,$func_arg_list = array()){
    
    $call_handler = null;
    $list = $func_arg_list[0];
    $method_list = array_slice($func_arg_list,1);
    
    $is_valid_list = false;
    if(!empty($method_list)){
      
      $is_valid_list = is_array($list);
      if(empty($is_valid_list) && is_object($list)){
      
        // http://www.php.net/manual/en/reflectionclass.isiterateable.php
        $rclass = new ReflectionClass(get_class($val));
        $is_valid_list = $rclass->isIterateable();
      
      }//if
      
    }//if
    
    if($is_valid_list){
      
      $call_handler = self::getCall($method);
      $format_handler = new out_format($call_handler->config());
      $output = array();
      
      foreach($list as $key => $val){
      
        foreach($method_list as $method){
        
          if(is_object($val)){
          
            $output[] = sprintf('%d - %s',$key,$format_handler->wrap('span',get_class($val),out_config::COLOR_INDEX));
          
            if($method[0] == '-'){
          
              // the method is something like: ->getThis()->get() so eval it...
              $arg_handler = new out_arg($method,eval(sprintf('return $val%s;',$method)));
              $arg_handler->config($call_handler->config());
              $output[] = $format_handler->indent("\t",$arg_handler->out());
          
            }else{
              
              if(method_exists($val,$method)){
              
                $arg_handler = new out_arg(sprintf('->%s()',$method),call_user_func(array($val,$method)));
                $arg_handler->config($call_handler->config());
                $output[] = $format_handler->indent("\t",$arg_handler->out());
                
              }else{
                $output[] = $format_handler->indent("\t",sprintf('->%s() undefined',$method));
              }//if/else
              
            }//if/else
            
          }else{
          
            $output[] = sprintf('%d - %s',$key,$format_handler->wrap('span',$val,out_config::COLOR_INDEX));
          
            // since $val isn't an object, then use it as the value to pass to a function...
            $arg_handler = new out_arg(sprintf('%s(%s)',$method,$val),call_user_func($method,$val));
            $arg_handler->config($call_handler->config());
            $output[] = $format_handler->indent("\t",$arg_handler->out());          
          
          }//if/else
        
        }//foreach
      
      }//foreach
      
      $output[] = '';
      
      $arg_handler = new out_arg('',join("\r\n",$output));
      $arg_handler->type(out_arg::TYPE_STRING_GENERATED);
      $call_handler->set($arg_handler);
      
    }//if
      
    return $call_handler;
    
  }//method
  
  /**
   *  similar to {@link p()} but returns string
   */
  static function sp($title = null){
    return self::put(self::pHandle(__METHOD__,$title,false),self::OUT_STR);
  }//method
  
  /**
   *  similar to {@link p()} but outputs to a file instead
   */
  static function fp($title = null){
    return self::put(self::pHandle(__METHOD__,$title,false),self::OUT_FILE);
  }//method

  /**
   *  some simple profiling
   *  
   *  this method goes in calls of 2, so the first call activates it, the second
   *  will print out the execution time with the passed in title(s)       
   *  
   *  @since  11-08-09
   */ 
  static function p($title = null){
    return self::put(self::pHandle(__METHOD__,$title),self::OUT_SCREEN);
  }//method
  
  /**
   *  handles the p* calls
   *  
   *  @param  string  $method the externally called method
   *  @param  string  $title  the title of the profile
   *  @param  boolean $is_html  if true, then output is html, false then output is plaintext   
   *  @return out_call   
   */
  private static function pHandle($method,$title,$is_html = true){
    
    $ret_call = null;
    if(empty(self::$PROFILE_STACK) || ($title !== null)){
      
      $profile_map = array();
      $call_handler = self::getCall($method);
      if(!$is_html){ $call_handler->config()->outType(out_config::OUT_TXT); }//if
      
      $profile_map['start_path'] = sprintf('%s:%s',$call_handler->file()->path(),$call_handler->file()->line());
      $profile_map['start'] = microtime(true);
      if(empty($title)){
        $profile_map['title'] = basename($profile_map['start_path']);
      }else{
        $profile_map['title'] = $title;
      }//if/else
      self::$PROFILE_STACK[] = $profile_map;
    
    }else{
    
      $profile_map = array_pop(self::$PROFILE_STACK);
      if(!empty($profile_map)){
      
        $stop = microtime(true);
        $call_handler = self::getCall($method);
        if(!$is_html){ $call_handler->config()->outType(out_config::OUT_TXT); }//if
        
        $format_handler = new out_format($call_handler->config());
        
        // get the execution time in milliseconds...
        $time = round((($stop - $profile_map['start']) * 1000),2);
        
        // go through and build a path...
        $title = '';
        foreach(self::$PROFILE_STACK as $key => $map){
          $title .= sprintf('%s > ',$map['title']);
        }//foreach
        $title .= $format_handler->wrap('b',$profile_map['title']);
        
        $start_file = new out_file($profile_map['start_path']);
        $start_file->config($call_handler->config());
      
        $arg_handler = new out_arg('',
          sprintf("%s = %s ms\r\n\tStart: %s %s\r\n\tStop: %s ",
            $title,
            $time,
            $profile_map['start'],
            $start_file->out(true),
            $stop
          )
        );
        
        $arg_handler->type(out_arg::TYPE_STRING_GENERATED);
        $call_handler->set($arg_handler);
        $ret_call = $call_handler;
        
      }//if
    
    }//if/else
    
    return $ret_call;
    
  }//method
  
  /**
   *  similar to {@link h()} but returns string
   */
  static function sh($count = 0){
    return self::put(self::hHandle(__METHOD__,$count),self::OUT_STR);
  }//method
  
  /**
   *  similar to {@link h()} but outputs to a file instead
   */
  static function fh($count = 0){
    return self::put(self::hHandle(__METHOD__,$count),self::OUT_FILE);
  }//method

  /**
   *  prints here and the count, or the line number is count is 0, useful for
   *  finding where in the code the error is thrown...
   *
   *  @param  integer $count  the count you want
   */
  static function h($count = 0){
    return self::put(self::hHandle(__METHOD__,$count),self::OUT_SCREEN);
  }//method
  
  /**
   *  handles the t* calls
   *  
   *  @param  string  $method the externally called method
   *  @return out_call   
   */
  private static function hHandle($method,$count = 0){
  
    $call_handler = self::getCall($method);
    $count = empty($count) ? $call_handler->file()->line() : $count;
    
    $arg_handler = new out_arg('',sprintf('here %d',$count));
    $arg_handler->type(out_arg::TYPE_STRING_LITERAL);
    $call_handler->set($arg_handler);
    return $call_handler;
    
  }//method
  
  /**
   *  this is just a shortcut method because out::isHtml(false) is so much easier
   *  than out::setType(out_config::OUT_TXT);
   *  
   *  @param  boolean $bool true to turn html on, false to turn to plain text
   */
  static function isHtml($bool){
  
    if($bool){
      self::setType(out_config::OUT_HTML);
    }else{
      self::setType(out_config::OUT_TXT);
    }//if/else
  
  }//method
  
  /**
   *  set the output type for the class, currently this is private but in the future
   *  more formats might be supported (eg, xml,json) which would make this function
   *  more important, right now though {@link isHtml()} is enough for the class
   */
  private static function setType($type){
    self::$OUT_DEFAULT = $type;
  }//method
  
  /**
   *  set whether out should bother printing objects or just print what type of object they are
   * 
   *  this is to limit frustration when you are working with a lot of huge objects which are impractical to
   *  print out, if you still need to see what is in the object then use {@link i()} as that just prints
   *  info about the object and always works
   *  
   *  @param  boolean                  
   */
  static function setPrintObject($bool){
    self::$PRINT_OBJECTS = $bool;
  }//method
  
  /**
   *  gets the current configuration object for $this class
   *  
   *  @return out_config  the current configuration      
   */
  private static function getConfig(){
  
    $config = new out_config();
    $config->outType(self::$OUT_DEFAULT);
    $config->outObject(self::$PRINT_OBJECTS);
    return $config;
  
  }//method
  
  /**
   *  handles output of the $call_handler
   *  
   *  @param  out_call  $call_handler the call object with all the call's info to be output 
   *  @param  integer $out_type how you want the output handled       
   *  @return mixed
   */
  private static function put($call_handler,$out_type = self::OUT_SCREEN){
  
    // canary...
    if(empty($call_handler)){ return false; }//if
  
    $ret_mix = true;
  
    switch($out_type){
    
      case self::OUT_SCREEN:
  
        if($call_handler->config()->isCli()){
        
          // if we're in cli write to std_err instead of std_out...
          $fp = fopen('php://stderr','a+');
          fwrite($fp,$call_handler->out());
          fclose($fp);
        
        }else{
          
          echo $call_handler;
          
        }//if/else
        
        break;
        
      case self::OUT_FILE:
      
        // we want plain text for the file...
        $call_handler->config()->outType(out_config::OUT_TXT);
      
        if(self::$first_file_call){
        
          // first get the args from the call handler...
          $arg_list = $call_handler->get();
          // now combine the args with a generated break with the date as a title for a nice timestamp in the output file...
          $b_call_handler = self::bHandle(__METHOD__,array(date(DATE_RFC822),3));
          $arg_list = array_merge($b_call_handler->get(),$arg_list);
          $call_handler->set($arg_list);
          self::$first_file_call = false; // after this, all other file calls are not the first
          
        }//if
          
        file_put_contents(self::$OUT_TO,$call_handler->out(),(FILE_APPEND | LOCK_EX));
        break;
        
      case self::OUT_STR:
      
        // we want plain text for the file...
        $call_handler->config()->outType(out_config::OUT_TXT);
        $ret_mix = $call_handler->out();
        
    }//switch
    
    return $ret_mix;
  
  }//method
  
  /**
   *  gets the call handler that contains all the information for the call
   *  
   *  @param  atring  $method the external method that was called
   *  @param  array $func_arg_list  the values of all the passed in args for the external $method call
   *  @return out_call
   */        
  private static function getCall($method,$func_arg_list = array()){
    
    $call_handler = new out_call($method,$func_arg_list); 
    $call_handler->config(self::getConfig());
  
    return $call_handler;
    
  }//method



}//class

class out_config_base extends out_base {

  /**
   *  get/set an out_config object
   *  
   *  @param  out_config  $val      
   *  @return out_config  null if no $val has been set before
   */
  function config($val = null){
  
    $default_val = null;
    if($val === null){
      $default_val = new out_config();
    }//if
  
    return $this->val('out_config_base::config',$val,$default_val);
  
  }//method
  function hasConfig(){ return $this->has('out_config_base::config'); }//method

}//class

/**
 *  the base class for all the getting/setting out_* classes
 *  
 *  @version 0.1
 *  @author Jay Marcyes {@link http://marcyes.com}
 *  @since 10-13-09
 *  @project out
 ******************************************************************************/   
class out_base {

  /**
   *  holds all the values this instance can set
   *
   */        
  protected $val_map = array();

  /**
   *  blanket function that does the getting/setting of values.
   *  
   *  if $val is null, then the $key's current value is checked if it exists, if
   *  it doesn't exist then $default_val is returned
   *  @access private   
   *  
   *  @param  string|integer  $key  the key whose value you want to fetch
   *  @param  mixed $val  the val you want to set key to, if null then key is returned
   *  @Param  mixed $default_val  if $key isn't found, then return $default_val   
   *  @return mixed if $val is null then the $key's val is returned, otherwise returns nothing
   */     
  protected function val($key,$val = null,$default_val = null){
    if($val === null){
      return isset($this->val_map[$key]) ? $this->val_map[$key] : $default_val; 
    }else{
      $this->val_map[$key] = $val;
    }//if/else
  }//method
  
  /**
   *  clear a $key from the {@link $val_map}
   */
  protected function clear($key){
    if(isset($this->val_map[$key])){ unset($this->val_map[$key]); }//if
  }//method
  
  /**
   *  check if a given key is in the {@link $val_map} and non-empty
   *  @return boolean true if key exists and is non-empty
   */
  protected function has($key){
    return !empty($this->val_map[$key]);
  }//method

}//class

/**
 *  handle all the information of the external out call
 *  
 *  for a call like out::e($var1,$var2,$var3)) this class would have the method() (out::e)
 *  and the names and values of the 3 passed in $var variables. 
 *  
 *  @version 0.1
 *  @author Jay Marcyes {@link http://marcyes.com}
 *  @since 10-13-09
 *  @package  out 
 ******************************************************************************/    
class out_call extends out_config_base implements IteratorAggregate {

  function __construct($method = '',$arg_val_list = array()){

    $this->set($this->parse($method,$arg_val_list));
  
  }//method
  
  function file($val = null){ return $this->val('file',$val,null); }//method
  function hasFile(){ return $this->has('file'); }//method
  
  /**
   *  return a stack trace of the current call
   *  
   *  @param  integer $history  how many lines back you want to get back   
   *  @return string  a nicely formatted stacktrace suitable for output
   */
  function outTrace($history = 0){
  
    // canary...
    if(!$this->hasTrace()){ return ''; }//if

    $format_handler = new out_format($this->config());
  
    $trace_list = $this->trace();
    $trace_count = count($trace_list);
    
    $trace_lines = array();
    
    $method = empty($trace_list[($trace_count - 2)]) 
      ? 'unknown::unknown()' 
      : $trace_list[($trace_count - 2)]->getMethod();
    
    $trace_lines[] = $format_handler->wrap('b',$method);
    $trace_lines[] = "\tbacktrace:";
    
    // get rid of the last call since it is the out call...
    $trace_list = array_slice($trace_list,0,($trace_count - 1));
    // reverse the list so last call is at the top...
    $trace_list = array_reverse($trace_list,true);
    $trace_history = 0;
    foreach($trace_list as $key => $file_map){
    
      // canary, only go for the amount of rows requested...
      if(($history > 0) && ($trace_history >= $history)){ break; }//if
    
      $file_map->config($this->config());
      $trace_lines[] = sprintf("\t\t%'02d - %s\t\t%s",$key,$file_map->out(false,false),$file_map->getMethod());
      $trace_history++;
    
    }//foreach
    
    $trace_lines[] = ''; // we want a newline at the end also
    
    return implode(PHP_EOL,$trace_lines);
    
  }//method
  
  function method($val = null){ return $this->val('out_args::method',$val,''); }//method
  function hasMethod(){ return $this->has('out_args::method'); }//method
  
  function trace($val = null){ return $this->val('out_args::trace_list',$val,array()); }//method
  function hasTrace(){ return $this->has('out_args::trace_list'); }//method
  
  function get(){ return $this->val('out_args::arg_list',null,array()); }//if
  
  function set($arg){
  
    if(is_array($arg)){
    
      foreach(array_keys($arg) as $key){
        $arg[$key]->config($this->config());
      }//foreach
    
      $this->val('out_args::arg_list',$arg,array());
      
    }else{
    
      if($arg instanceof out_arg){
      
        $this->set(array($arg));
      
      }//if
    
    }//if/else
  
  }//method
  
  function add($arg){
  
    if(is_array($arg)){
    
      $arg_list = $this->get();
      $this->set(array_merge($arg_list,$arg));
      
    }else{
    
      if($arg instanceof out_arg){
      
        $arg_list = $this->get();
        $arg_list[] = $arg;
        $this->set($arg_list);
      
      }//if
    
    }//if/else

  }//method
  
  function config($val = null){
  
    $ret_val = parent::config($val);
  
    if($val !== null){
    
      // update the config and file into all the args...
      $this->file()->config($val);
      
      $arg_list = $this->get();
      $this->set($arg_list);
    
    }//if
    
    return $ret_val;
  
  }//method
  
  function __toString(){ return $this->out(); }//method
  function out(){
  
    $ret_str = array();
    $format_handler = new out_format($this->config());
    
    $pre_style = 'text-align: left; padding-left: 10px;';
    $pre_style .= 'white-space: pre-wrap;'; /* css-3 */
    $pre_style .= 'white-space: -moz-pre-wrap;'; /* Mozilla, since 1999 */
    $pre_style .= 'white-space: -pre-wrap;'; /* Opera 4-6 */
    $pre_style .= 'white-space: -o-pre-wrap;'; /* Opera 7 */
    $pre_style .= 'word-wrap: break-word;'; /* Internet Explorer 5.5+ */
    $pre_style .= 'font: inherit;';
    
    foreach($this as $arg){
    
      $arg_str = '';
      if($this->config()->outInfo()){
        $arg_str = $arg->outInfo($this->file()->className());
      }else if($this->config()->outChar()){
        $arg_str = $arg->outChar();
      }else if($this->config()->outMem()){
        $arg_str = $arg->outMem();
      }else{
        $arg_str = $arg->out();
      }//if/else
    
      ///$ret_str[] = $format_handler->wrap('pre',sprintf("%s %s",$arg_str,$this->file()->out(true,false)),$pre_style);
      $ret_str[] = $arg_str;
    
    }//foreach
  
    ///$ret_str[] = '';
    ///return join("\r\n\r\n",$ret_str);
    
    return $format_handler->wrap(
      'pre',
      sprintf("%s %s",join("\r\n\r\n",$ret_str),$this->file()->out(true,false)),
      $pre_style
    )."\r\n\r\n";

  }//method

  /**
   *  required method to implement IteratorAggregate
   *   @return  ArrayIterator   
   */
  function getIterator(){
    return new ArrayIterator($this->get());
  }//method

  /**
   *  combine the passed in names with their corresponding values
   *  
   *  @param  string  $method the method that was originally called
   *  @param  array $arg_val_list the argument values that where pased to $method         
   *  @return array a list of out_arg instances
   */        
  private function parse($method,$arg_val_list = array()){
  
    // canary...
    if(empty($method)){ return array(); }//if
  
    $ret_list = array();
    $this->method($method);
    list($class_name,$func_name) = explode('::',$method);
    
    $backtrace = null;
    $backtrace_list = array_reverse(debug_backtrace()); // we want to go latest to first
    $last_backtrace = 0; ///(count($backtrace_list) - 1);
    $trace_list = array();
    
    //possible 'included' functions
    $include_list = array('include','include_once','require','require_once');
    
    //check for any included/required files. if found, get array of the last included file (they contain the right line numbers)
    ///for($i=$last_backtrace; $i>=0; $i--){
    for($i = 0; $i < count($backtrace_list); $i++){
      
      /* $trace_list[] = new out_file(
        empty($backtrace_list[$i]['file']) ? 'unknown' : $backtrace_list[$i]['file'],
        empty($backtrace_list[$i]['line']) ? 'unknown' : $backtrace_list[$i]['line'],
        empty($backtrace_list[$i]['class']) ? 'unknown' : $backtrace_list[$i]['class'],
        empty($backtrace_list[$i]['function']) ? 'unknown' : $backtrace_list[$i]['function'],
        empty($backtrace_list[$i]['args']) ? array() : $backtrace_list[$i]['args']
      ); */
      
      $trace_list[] = new out_file(
        empty($backtrace_list[$i]['file']) ? '' : $backtrace_list[$i]['file'],
        empty($backtrace_list[$i]['line']) ? '' : $backtrace_list[$i]['line'],
        empty($backtrace_list[$i]['class']) ? '' : $backtrace_list[$i]['class'],
        empty($backtrace_list[$i]['function']) ? '' : $backtrace_list[$i]['function'],
        empty($backtrace_list[$i]['args']) ? array() : $backtrace_list[$i]['args']
      );
      
      // canary...
      if(isset($backtrace_list[$i]['function']) 
        && (in_array($backtrace_list[$i]['function'], $include_list) || (strcasecmp($backtrace_list[$i]['function'], $func_name) != 0))
      ){ continue; }//if

      $backtrace = $backtrace_list[$i];
      $last_backtrace = ($i - 1);
      
      break;

    }//for
    
    $this->trace($trace_list);
    
    if(!empty($backtrace)){

      $line = ($backtrace['line']-1);
      $code = '';
      
      if(is_file($backtrace['file'])){
        
        $file_lines = file($backtrace['file']);
        if(!empty($file_lines)){
          // get the code with the function call, multiline strings need to be rebuilt...
          $code = $file_lines[$line--];
          while(!preg_match('/'.$class_name.'::'.$func_name.'/iu',$code) && ($line >= 0)){
            $code = $file_lines[$line--].$code;
          }//while
        }//if
      }//if
      
      // store the file and line number...
      $file_map = new out_file(
        $backtrace['file'],
        $backtrace['line'],
        isset($backtrace[$last_backtrace]['class']) ? $backtrace[$last_backtrace]['class'] : ''
      );
      $this->file($file_map);
      
      if(!empty($arg_val_list)){

        //find call to this class, parse out the func vars...
        $matches = array();
        if(!empty($code) && preg_match_all('/'.$class_name.'::'.$func_name.'\s*\((.*?)\);/ius', $code, $matches)){
        
          foreach($matches[1] as $key => $match){
          
            if(empty($match)){
            
              // the function call didn't have any vars, so just add it to the list...
              $ret_list[] = new out_arg('',$arg_val_list[0]);
              
            }else{
            
              // split the func args by the comma and add them to the list...
              $func_args = $this->parseVarNames($match);
              foreach($func_args as $key => $func_arg){
              
                $ret_list[] = new out_arg(trim($func_arg),$arg_val_list[$key]);
              
              }//foreach
              
            }//if/else
        
          }//foreach
          
        }else{
          
          if(isset($backtrace['args'])){
            
            foreach($backtrace['args'] as $key => $arg){
            
              $ret_list[] = new out_arg('',$arg_val_list[$key]);
            
            }//foreach
          
          }else{
          
            $ret_list[] = new out_arg('',$arg_val_list[0]);
            
          }//if/else
        
        }//if/else
        
      }//if
        
    }//if
    
    return $ret_list;
  
  }//method
  
  /**
   *  parse the varnames from the found string between the calling method's parens
   * 
   *  since a method can be called with multiple var names and types (eg, e($one,'a string',trim($four)))
   *  they have to be parsed into their individual names so they can be accurately identified
   * 
   *  @internal this used to be accomplished with the regex '/,(?=\s*(?:\$[a-zA-Z_]|[\'\"]))/u' but it
   *    would get confused with nested functions and I couldn't see any way to fix the regex so I have
   *    resorted to a good ole' fashioned parser
   *
   *  @param string $input the string from {@link getVarInfo()} parsed from the method call
   *  @return array list of all the variable names found
   */
  private function parseVarNames($input){
  
    $ret_list = array();
    for($i = 0, $max = mb_strlen($input); $i < $max ;$i++){
      $name = '';
      switch($input[$i]){
      
        case '"': // we have a string...
        case "'":
          list($str,$i) = $this->resolveStr($i,$input,$max);
          $name .= $str;
          $ret_list[] = $name;
          break;
        
        case '(':
          list($str,$i) = $this->resolveParen($i,$input,$max);
          $name .= $str;
          $ret_list[] = $name;
          break;
        
        case ',': // just skip right past commas
          break;
        
        case '$': // we have a variable or class...
        default: // either a space, or the beginning of a function
        
          // get passed any whitespace...
          while(ctype_space($input[$i])){ $i++; }//while
        
          list($str,$i) = $this->resolveVar($i,$input,$max);
          $name .= $str;
          $ret_list[] = $name;
          break;
          
      }//switch
      
    }//for
  
    return $ret_list;
  
  }//method
  
  /**
   *  this is a helper function for parseVarNames. It gets either a $var or static method call
   *     
   *  @param  integer $i  where in $input we are
   *  @param  string  $input  the input being scanned
   *  @param  integer $max  the size of input
   *  @return array array($name,$i) $name is the part of input found, 
   *                $i is where the var/method ended
   */
  private function resolveVar($i,$input,$max){
    $name = '';
    // go until a comma is hit
    while(($i < $max) && ($input[$i] != ',')){
      if(($input[$i] == '"') || ($input[$i] == "'")){
        list($str,$i) = $this->resolveStr($i,$input,$max);
        $name .= $str;
      }else if($input[$i] == '('){
        list($str,$i) = $this->resolveParen($i,$input,$max);
        $name .= $str;
      }else{
        $name .= $input[$i];
      }//if/else if/else
      $i++;
    }//while
    return array($name,$i);
  }//method
  
  /**
   *  this is a helper function for parseVarNames. It gets from one paren to the next
   *     
   *  @param  integer $i  where in $input we are, should be a ( most likely
   *  @param  string  $input  the input being scanned
   *  @param  integer $max  the size of input               
   *  @return array array($name,$i) $name is the part of input between the parens, 
   *                $i is where the last paren was found
   */        
  private function resolveParen($i,$input,$max){
    $name = '';
    $paren_count = 0;
    while($i < $max){
      if($input[$i] == '('){
        $paren_count++;
        $name .= $input[$i];
      }else if($input[$i] == ')'){
        $paren_count--;
        $name .= $input[$i];
        if($paren_count <= 0){ break; }//if
      }else if(($input[$i] == '"') || ($input[$i] == "'")){
        list($str,$i) = $this->resolveStr($i,$input,$max);
        $name .= $str;
      }else{
        $name .= $input[$i];
      }//if/else if...
      $i++;
    }//while
    return array($name,$i);
  }//method
  
  /**
   *  this is a helper function for parseVarNames. It gets a full string of either ' or "
   *  @param  integer $i  where in $input we are, this should be either ' or "   
   *  @param  string  $input  the input being scanned
   *  @param  integer $max  the size of input
   *  @return array array($name,$i) $name is the found string between quotes, 
   *                $i is where the last paren was found
   */
  private function resolveStr($i,$input,$max){
    $name = '';
    ///$name = $input[$i]; // save the first '
    ///$trigger = $input[$i]; // the ' or the " is the trigger we'll look for to get out
    ///$i++; // go past the enquote char
    $is_str = false;
    $slash_i = -500; // needs to start smaller than -1 to clear checks in while loop
    $trigger = '';
    
    while($i < $max){
    
      switch($input[$i]){
        
        case '"': // we have a string...
        case "'":
        
          // save the trigger
          if(!$is_str){ $trigger = $input[$i]; }//if
        
          $name .= $input[$i];
          
          // if we've found a trigger, only bounce out of the string if not escaped...
          if($input[$i] === $trigger){
            if(($i - 1) !== $slash_i){ $is_str = !$is_str; }//if
          }//if
          
          break;

        case ',':
        
          // we're done if we hit a comma not in a string...
          if($is_str){ 
          
            $name .= $input[$i];
            break;
            
          }else{

            break 2;
            
          }//if

        case '\\':
          $name .= $input[$i];
          $slash_i = $i;
          break;

        case '(':
          list($str,$i) = $this->resolveParen($i,$input,$max);
          $name .= $str;
          break;
        
        default:
        
          if($is_str){
          
            $name .= $input[$i];
            
          }else{
          
            // get passed any whitespace...
            while(ctype_space($input[$i])){ $i++; }//while
          
            if(isset($input[$i]) && ($input[$i] === '.')){
            
              list($str,$i) = $this->resolveVar($i,$input,$max);
              $name .= $str;
              
            }else{
              break 2;
            }//if/else
            
          }//if/else
          
          break;
          
      }//switch
      
      $i++;
      
    }//while
    
    return array($name,--$i);
  }//method

}//class

class out_arg extends out_config_base {

  /**#@+
   *  all the different types the arg can be
   *  @var  integer
   */
  const TYPE_UNDEFINED = 0;
  const TYPE_NULL = 1;
  /**
   *  for normal $vars with a string value 
   */
  const TYPE_STRING_VAL = 2;
  /**
   *  for passed in vars that are strings, (eg, "this was the value passed in")
   */     
  const TYPE_STRING_LITERAL = 3;
  /**
   *  certain times strings will be created and added as an arg to be printed out, this
   *  type is so those string don't go through the normal escaping process since they are
   *  generated on the fly with stuff like HTML, etc.      
   */     
  const TYPE_STRING_GENERATED = 4;
  const TYPE_NUMERIC = 5;
  const TYPE_ARRAY = 6;
  const TYPE_OBJECT = 7;
  const TYPE_BOOLEAN = 8;
  /**
   *  used to output breaks, see {@link outBreak()} and {@link out::b()}
   */     
  const TYPE_BREAK = 9;
  /**
   *  this is really similar to TYPE_STRING_GENERATED, except for vars, so the varname
   *  can be printed out but the value doesn't go through escaping   
   */
  const TYPE_VAR_GENERATED = 10;
  /**#@-*/
  
  /**
   *  used for indent in functions like {@link aIter()} and {@link outInfo()}
   *  @var  string   
   */     
  private $indent = "\t";

  function __construct($name = '',$val = null){
  
    $this->name($name);
    $this->value($val);
  
  }//method

  function type($val = null){ return $this->val('type',$val,self::TYPE_UNDEFINED); }//method
  
  function name($val = null){ return $this->val('name',$val,''); }//method
  function hasName(){ return $this->has('name'); }//method
  
  ///function printName($val = null){ return $this->val('print_name',$val,true); }//method
  
  function useName($val = null){ return $this->val('use_name',$val,true); }//method
  
  function defaultValue($val = null){ return $this->val('value_default',$val,''); }//method
  
  function printValue($val = null){ return $this->val('out_arg::value_print',$val,$this->value()); }//method
  function hasPrintValue(){ return $this->has('out_arg::value_print'); }//method
  
  /**
   *  get/set the value of the arg
   *  
   *  $val could be NULL which messes everything up
   *      
   *  this is different than most of the get/set functions that just wrap {@link out_base::val()} because
   *  it has to compensate for being able to have null values for the value, which causes isset() to fail
   *  
   *  @param  mixed $val  if $val is passed in then set value to $val
   *  @return mixed the currently set value
   */
  function value(){
    // have to compensate for $val actually being passed in with a value of NULL...
    $val = func_get_args();
    if(empty($val)){
      return array_key_exists('out_arg::value',$this->val_map)
        ? $this->val_map['out_arg::value']
        : '';
    }else{
      $this->val_map['out_arg::value'] = $val[0];
      $this->setType();
    }//if/else
  }//method
  
  /**
   *  auto discover the type, this is called when the arg is going to be output but 
   *  the type can also be explicitely set by using {@link type()}
   *  
   *  @return integer one of the TYPE_* constants
   */        
  private function setType(){
  
    $ret_int = $this->type();
    $default_val = '';
    if(empty($ret_int)){
    
      $val = $this->value();
      
      if(is_null($val)){
      
        $ret_int = self::TYPE_NULL;
        $default_val = 'NULL';
        $this->printValue($default_val);
        
      }else if(is_bool($val)){
        
        $ret_int = self::TYPE_BOOLEAN;
        $default_val = $val ? 'TRUE' : 'FALSE';
        $this->printValue($default_val);
        
      }else if(is_numeric($val)){
      
        $ret_int = self::TYPE_NUMERIC;
        $default_val = $val;
        
      }else if(is_object($val)){
      
        $ret_int = self::TYPE_OBJECT;
        $default_val = get_class($val).' instance';
        
      }else if(is_array($val)){
      
        $ret_int = self::TYPE_ARRAY;
        $default_val = 'array()';
        
      }else if(is_string($val)){
        
        $ret_int = self::TYPE_STRING_VAL;
        $name = $this->name();
        
        $regex = '#^[\'"]{2}(?:\.(\d+))?$#u'; // technically it isn't possible to do ''.5 which is a shame
        $match = array();
        if(preg_match($regex,$name,$match)){
        
          $ret_int = self::TYPE_BREAK;
          $this->name(isset($match[1]) ? $match[1] : '');
          $this->useName(false);
        
        }else if($this->isStrLiteralName($name)){
        
          $ret_int = self::TYPE_STRING_LITERAL;
          $this->useName(false);
          
        }//if/else if
        
        ///$format_handler = new out_format($this->config());
        ///$default_val = $format_handler->enquote(empty($val) ? '' : $val);
        $default_val = empty($val) ? '""' : sprintf('"%s"',$val);
        
      }//if/else if
    
      $this->type($ret_int);
      $this->defaultValue($default_val);
    
    }//if
  
    return $ret_int;
  
  }//method
  
  /**
   *  tests whether $name is actually just a string, this is tricky because it
   *  has to test concatenation and stuff
   *  
   *  @param  string  $name the argument's name
   *  @return boolean true if an actual string and not something like a method call
   */
  private function isStrLiteralName($name){
  
    // canary...
    if(empty($name)){ return false; }//if
  
    $ret_bool = false;
    $name = trim($name);
    
    if(($name[0] === '"') || ($name[0] === "'")){
      $ret_bool = true;
    }else{
    
      $name_bits = explode('.',$name);
      $bits_count = count($name_bits);
      
      if($bits_count > 1){
        // check if the string is in parens, if it isn't, then it's a string literal...
        if(!preg_match('#[\[\(]#u',$name_bits[0]) || !preg_match('#[\]\)]#u',$name_bits[$bits_count - 1])){
          $ret_bool = true;
        }//if
      }//if
      
    }//if/else
    
    // this started failing...
    ///preg_match('#^[\'"]|[\'"](?!.*[\'"])(?!\s*(?:\]|\)))#u',trim($name))
  
    return $ret_bool;
  
  }//method
  
  function __toString(){ return $this->out(); }//method
  
  function out(){
  
    $type = $this->type();
    switch($type){
    
      case self::TYPE_NULL: 
      
        $this->useName(true);
        $this->config()->outEnquote(false);
        $ret_str = $this->outVar();
        break;
        
      case self::TYPE_STRING_VAL: 
      
        $this->useName(true);
        $this->config()->outEnquote(true);
        $ret_str = $this->outVar();
        break;
        
      case self::TYPE_STRING_LITERAL:
        
        $this->useName(false);
        $this->config()->outEnquote(true);
        $ret_str = $this->outVar();
        break;
        
      case self::TYPE_STRING_GENERATED:
        
        $this->useName(false);
        $this->config()->outEnquote(false);
        $this->config()->outEscape(false);
        $ret_str = $this->outVar();
        break;
        
      case self::TYPE_VAR_GENERATED:
        
        $this->useName(true);
        $this->config()->outEnquote(false);
        $this->config()->outEscape(false);
        $ret_str = $this->outVar();
        break;
        
      ///case self::TYPE_STRING_EMPTY: break;
      case self::TYPE_NUMERIC:
      
        $this->useName(true);
        $this->config()->outEnquote(false);
        $ret_str = $this->outVar();
        break;
      
      case self::TYPE_ARRAY:
      case self::TYPE_OBJECT:
      
        $this->useName(true);
        $this->config()->outEnquote(false);
        $ret_str = $this->outArray();
        break;
      
      case self::TYPE_BOOLEAN:
      
        $this->useName(true);
        $this->config()->outEnquote(false);
        $ret_str = $this->outVar();
        break;
      
      case self::TYPE_BREAK:
    
        $this->useName(false);
        $this->config()->outEnquote(false);
        $ret_str = $this->outBreak();
        break;
    
      case self::TYPE_UNDEFINED:
      default:
        break;
    
    }//switch
    
    return $ret_str;
  
  }//method
  
  /**
   *  prints out information about the $this arg
   *
   *  @since  9-23-09
   *  
   *  object info uses the reflection class http://nz.php.net/manual-lookup.php?pattern=oop5.reflection&lang=en  
   *  
   *  @todo
   *    - get the parent classes methods and values and stuff, then organize the methods by visible and invisible (private, protected)
   *    - this might be a good way for aIter to display classes also, but just get the properties of the object
   *
   *  @param  string  $calling_class  the class name of the class that made the call
   *  @return string  information about the arg                        
   */
  function outInfo($calling_class = ''){
  
    $this->useName(false);
    $config = $this->config();
    $config->outEnquote(false);
    $config->outObject(false);
  
    $type = $this->type();
    $format_handler = new out_format($config);
    $name = $this->name();
    $val = $this->value();
    $info_list = array();
    $info_type = 'undefined';
  
    switch($type){
    
      case self::TYPE_NULL: 
      
        $info_type = 'NULL';
        break;
      
      case self::TYPE_STRING_VAL:         
      case self::TYPE_STRING_LITERAL:
      case self::TYPE_STRING_GENERATED:
        
        $info_type = 'string';
        $info_list[] = sprintf('value: %s',$format_handler->enquote($val));
        $info_list[] = sprintf('%d characters',mb_strlen($val));
        break;
        
      ///case self::TYPE_STRING_EMPTY: break;
      case self::TYPE_NUMERIC:
      
        if(is_int($val)){
          $info_type = 'integer';
        }else if(is_float($val)){
          $info_type = 'float';
        }//if/else if
        
        $info_list[] = sprintf('value: %s',$val);
        
        break;
      
      case self::TYPE_ARRAY:
      
        $info_type = 'array';
        
        // find out if it is an indexed array...
        $info_list = array();
        $info_list[] = 'length: '.count($val);
        if(!empty($val)){
          $info_list[] = ctype_digit(join('',array_keys($val))) ? 'keys are numeric' : 'keys are associative';
        }//if
      
        break;
      
      case self::TYPE_OBJECT:
      
        $rclass = new ReflectionObject($val);
      
        $info_type = $rclass->getName().' instance';
        $indent = $this->indent;
        
        if($path = $rclass->getFileName()){
          $file_map = new out_file($path);
          $file_map->config($config);
          $info_list[] = sprintf(
            '%s %s %s',
            $format_handler->wrap('b','Defined:'),
            $rclass->getName(),
            $file_map->out(true,false)
          );
        }//if
        
        $class_name_list = array($rclass->getName());
        
        // get all the classes this object extends...
        if($parent_class = $rclass->getParentClass()){
          
          $info_list[] = $format_handler->wrap('b','Extends:');
          
          while(!empty($parent_class)){
          
            $class_name_list[] = $parent_class->getName();
            $file_map = new out_file($parent_class->getFileName());
            $file_map->config($config);
            $info_list[] = sprintf(
              '%s%s %s',
              $indent,
              $parent_class->getName(),
              $file_map->out(true,false)
            );
            
            $parent_class = $parent_class->getParentClass();
            
          }//while
            
        }//if
        
        // handle properties...
        $properties = $rclass->getProperties();
        $info_list[] = $format_handler->wrap('b',sprintf('%s (%d):','Properties',count($properties)));
        $prop_val = null;
        $prop_check = true;
        foreach($properties as $property){
        
          // setAccessible only around >5.3...
          if(method_exists($property,'setAccessible')){
          
            $property->setAccessible(true);
            $prop_val = $property->getValue($val);
            
          }else{
          
            if($property->isPublic()){
              $prop_val = $property->getValue($val);
            }else{
              $prop_val = $format_handler->wrap('i','Not Accessible');
              $prop_check = false;
            }//if/else
          
          }//if/else
          
          if(is_array($prop_val)){
            $prop_val = $format_handler->escapeArray(trim($this->aIter($prop_val,2,false)));
          }else{
            if($prop_check){
              $arg_map = new out_arg('',$prop_val);
              $prop_val = $arg_map->defaultValue();
            }//if
          }//if/else
          
          $info_list[] = sprintf('%s%s %s = %s',
            $indent,
            $format_handler->wrap('span',join(' ',Reflection::getModifierNames($property->getModifiers())),out_config::COLOR_MODIFIER),
            $format_handler->wrap('span',$property->getName(),out_config::COLOR_PARAM),
            $prop_val
          );
          
        }//foreach
        
        // handle methods...
        $methods = $rclass->getMethods();
        $info_list[] = $format_handler->wrap('b',sprintf('%s (%d):','Methods',count($methods)));
        $method_list = array();
        $only_public_methods = empty($calling_class)
          ? true 
          : !in_array($calling_class,$class_name_list);
        
        foreach($methods as $method){
          
          // we only want to show methods the person can use...
          if($only_public_methods && !$method->isPublic()){ continue; }//if
          
          $method_comment = $method->getDocComment();
          
          $params = $method->getParameters();
          $param_list = array();
          foreach($params as $param){
            
            $param_type = '';
            if(!empty($method_comment)){
              $match = array();
              if(preg_match(sprintf('#\*\s*@param\s+(\S+)\s+\$%s#iu',preg_quote($param->getName())),$method_comment,$match)){
                $param_type = $format_handler->wrap('span',$match[1],out_config::COLOR_TYPE).' ';
              }//if  
            }//if
            
            $param_str = $format_handler->wrap('span',sprintf('%s$%s',$param_type,$param->getName()),out_config::COLOR_PARAM);
            if($param->isDefaultValueAvailable()){
              $arg_map = new out_arg('',$param->getDefaultValue());
              $param_str .= ' = '.$arg_map->defaultValue();
            }//if
            $param_list[] = $param_str;
            
          }//foreach
        
          // see if we can get a return type for the method...
          $method_return_type = '';
          if(!empty($method_comment)){
            $match = array();
            if(preg_match('#\*\s*@return\s+(\S+)#iu',$method_comment,$match)){
              $method_return_type = ' '.$format_handler->wrap('span',$match[1],out_config::COLOR_TYPE);
            }//if  
          }//if
        
          $method_list[$method->getName()] = sprintf('%s%s%s %s(%s)',
            $indent,
            $format_handler->wrap('span',join(' ',Reflection::getModifierNames($method->getModifiers())),out_config::COLOR_MODIFIER),
            $method_return_type,
            $method->getName(),
            join(', ',$param_list)
          );
        
        }//foreach
        ksort($method_list);
        $info_list = array_merge($info_list,array_values($method_list));
        
        // handle constants...
        $constants = $rclass->getConstants();
        $info_list[] = $format_handler->wrap('b',sprintf('%s (%d):','Constants',count($constants)));
        foreach($constants as $const_name => $const_val){
          $info_list[] = sprintf('%s%s = %s',$indent,$format_handler->wrap('span',$const_name,out_config::COLOR_PARAM),$const_val);
        }//foreach
        
        break;
      
      case self::TYPE_BOOLEAN:
      
        $info_type = 'boolean';
        $info_list[] = sprintf('value: %s',$this->defaultValue());
        
        break;
      
      case self::TYPE_BREAK:
    
        $info_type = 'break';
        $info_list[] = sprintf('lines: %d',$this->name());
        break;
    
      case self::TYPE_UNDEFINED:
      default:
      
        $type = 'undefined';
      
        break;
    
    }//switch
    
    $this->printValue(sprintf("%s (%s)\r\n%s",
      $format_handler->wrap('b',$name),
      $info_type,
      empty($info_list) ? '' : join("\r\n",$info_list)."\r\n"
    ));
    
    return $this->outAll();
    
  }//method
  
  /**
   *  print out the chars using linux's od utility
   *  
   *  @since  2-26-09
   */
  function outChar(){
  
    // canary...
    // http://stackoverflow.com/questions/623776/does-php-have-a-function-to-detect-the-os-its-running-on
    if(mb_stripos(php_uname('s'),'windows') !== false){ return sprintf('%s does not support Octal Dump',php_uname('s')); }//if
    // aslo, you can try: http://www.php.net/manual/en/function.memory-get-usage.php#53174 ...
    ///if (substr(PHP_OS,0,3)=='WIN') {
  
    $ret_str = '';
  
    $this->useName(true);
    $config = $this->config();
    $config->outEnquote(false);
    $config->outObject(false);
    
    $val = $this->value();
    if(is_string($val)){
    
      $command = 'echo '.escapeshellarg($var).' | od -c';
      $output = array();
      exec($command,$output);
      $this->printValue(implode("\r\n",$output)."\r\n");
      
      $ret_str = $this->outAll();
      
    }//if
    
    return $ret_str;
  
  }//method
  
  /**
   *  print out the char's memory
   *  
   *  @since  12-7-09
   */
  function outMem(){
  
    // canary...  
    $ret_str = '';
    
    $val = $this->value();
    
    if(is_object($val)){
    
      $start_mem = memory_get_usage();
      $temp_val = clone $val;
      $stop_mem = memory_get_usage();
    
    }else{
    
      $start_mem = memory_get_usage();
      $temp_val = $val;
      $stop_mem = memory_get_usage();
    
    }//if/else
    
    // get rid of the temp val, we don't need it any more...
    unset($temp_val);
    
    // build config...
    $this->useName(true);
    $config = $this->config();
    $config->outEnquote(false);
    $format_handler = new out_format($config);
    
    $total_mem = $stop_mem - $start_mem;
    $this->printValue($format_handler->bytes($total_mem));
    
    $ret_str = $this->outAll();
    
    return $ret_str;
  
  }//method
  
  /**
   *  produces a divider line in input, the line is a bunch of equal signs.  
   *
   *  called publicly from out::b, or out::e(''), this creates a separator with a title, it is
   *  mainly handy for breaking up large blocks of output.
   *
   *  @internal this has not so much a bug, but a niggle, if you have a title that has
   *            a lot of thin chars and not a lot of wide ones (eg, w,m) and you have
   *            specified more than 1 $lines, then the title line might be shorter because
   *            even if it has the same amount of chars, they aren't as wide as =         
   *      
   *  @param  string  title the title you want to the separator to have
   *  @param  array $info_map the info_map created from {@link getVarInfo()}
   *  @param  integer $out_type the output type
   *  @param  integer $lines  how many lines the break should have            
   *  @return string  the created break block to be printed out
   */
  private function outBreak(){
  
    $ret_str = '';
    
    $lines = (int)$this->name();
    if(empty($lines)) { $lines = 1; }//if
    $half_lines = intval(floor($lines / 2));
    
    $title = $this->value();
    $title_len = 0;
    if(!empty($title)){
      $title = ' '.$title.' ';
      $title_len = mb_strlen($title);
    }//if
    
    $line_char = '=';
    $line_len = 60;
    $line_len = ($title_len > $line_len) ? $title_len : $line_len;
    $is_even = (($lines >= 2) && (($lines % 2) === 0)) ? true : false; 
    for($i = 0; $i < $half_lines ;$i++){ $ret_str .= str_pad('',$line_len,$line_char)."\r\n"; }//for
    $ret_str .= str_pad($title,$line_len,$line_char,STR_PAD_BOTH)."\r\n";
    $half_lines = ($is_even) ? ($half_lines - 1) : $half_lines;
    for($i = 0; $i < $half_lines ;$i++){ $ret_str .= str_pad('',$line_len,$line_char)."\r\n"; }//for
    
    $this->printValue($ret_str);
    
    return $this->outVar();
  
  }//method
  
  /**
   *  prints out the array or object
   *  
   *` @since  9-11-08 this function respects an object's __toString method      
   *
   *  @param  array|object  $array  to be printed out
   *  @param  array $info_map from {@link getVarInfo()}
   *  @param  integer $out_type   
   *  @return the array contents in nicely formatted string form
   */
  private function outArray(){
    
    $format_handler = new out_format($this->config());
    
    if($this->useName()){
      if(!$this->hasName()){
        $this->name('Array');
      }//if
    }//if
    
    $array = $this->value();
    
    $value = is_object($array) 
      ? $this->outObject($array,$this->config()->outObject()) 
      : $this->aIter($array,0,$this->config()->outObject());
    $value = $format_handler->escapeArray($value);
    $this->printValue($value);
    
    return $this->outAll();
    
  }//method
  private function aIter($array,$deep = 0,$out_object = false){
  
    $ret_str = 'Array ('.count($array).")\r\n(\r\n";
    $format_handler = new out_format($this->config());
  
    foreach($array as $key => $val){
      $ret_str .= "\t[".$key.'] => ';
      if(is_object($val)){
        $ret_str .= trim($format_handler->indent($this->indent,$this->outObject($val,$out_object)));
      }else if(is_array($val)){
        $ret_str .= trim($format_handler->indent($this->indent,$this->aIter($val,$deep + 1,$out_object)));
      }else{
      
        $ret_arg = new self('',$val);
        $ret_str .= $ret_arg->defaultValue();
    
      }//if/else
      $ret_str .= "\r\n";
    }//method

    $prefix = str_repeat($this->indent,($deep > 1) ? 1 : $deep);
  
    return trim($format_handler->indent($prefix,$ret_str.')'))."\r\n";
  
  }//method
  
  /**
   *  output an object
   *  
   *  @param  object  $obj  the object to output, this is different than outArray() and outVar() because
   *                        it can be called from {@link aIter()} 
   *  @param  boolean $out_object true if you want the object to be printed out, false if you just want the object
   *                              identified in output (a little cleaner)              
   *  @return string  the printValue of an object
   */
  private function outObject($obj,$out_object = false){
  
    $ret_str = '';
  
    if(method_exists($obj,'__toString')){
      $ret_str = get_class($obj).'::__toString() - '.$obj;
    }else{
      if($out_object){
        $ret_str = print_r($obj,1);
      }else{
        $ret_str = get_class($obj).' instance';
      }//if/else
    }//if/else
  
    return $ret_str;
  
  }//method
  
  
  private function outVar(){
  
    $ret_str = '';
    $format_handler = new out_format($this->config());
    
    if($this->useName()){
      if(!$this->hasName()){
        $this->name('Variable');
      }//if
    }//if
    
    $this->printValue($format_handler->escape($this->printValue()));
    
    return $this->outAll();
  
  }//method
  
  private function outAll(){
  
    $format_handler = new out_format($this->config());
  
    $ret_str = '';
    if($this->config()->outEnquote()){
      $ret_str = $format_handler->enquote($this->printValue());
    }else{
      $ret_str = $this->printValue();
    }//if/else
  
    if($this->useName() && $this->hasName()){
    
      $ret_str = sprintf("%s = %s",$format_handler->wrap('b',$this->name()),$ret_str);
    
    }//if
    
    return $ret_str;
  
  }//method
  
}//class

class out_format extends out_config_base {

  function __construct($config = null){
    
    $this->config($config);
  
  }//method

  /**
   *  indent all the lines of $val with $indent
   *  
   *  @param  string  $indent something like a tab
   *  @param  string  $val  the string to indent
   *  @return string  indented string
   */
  function indent($indent,$val){
    return preg_replace('#^(.)#mu',$indent.'\1',$val);
  }//method

  /**
   * decide if value should be encased in quotes (") or not
   *
   * @param string $val the value to quote   
   * @return string the enquoted, or not $val
   */
  function enquote($val){
  
    // canary...
    ///if(!$this->config()->outEnquote()){ return $val; }//if
    
    $start_quote = $stop_quote = $this->wrap('span','"',out_config::COLOR_ENQUOTE);
    
    return $this->wrap(array($start_quote,$stop_quote),$val);
    
  }//method

  function escapeArray($input){
  
    // canary...
    if(!$this->config()->isHtml()){ return $input; }//if
  
    $input = str_replace(array('<','>'),array('&lt;','&gt;'),$input);
    $pinput = preg_replace('/(?<=\s)\[[^\]]+\](?=\s+=&gt;)/u','<span style="color:'.out_config::COLOR_INDEX.'">\0</span>',$input);
    if(preg_last_error() == PREG_NO_ERROR){
      $input = $pinput; unset($pinput);
    }//if
  
    return $input;
  
  }//method

  /**
   *  html special char escapes the $input
   *
   *  @param  string  $input
   *  @return string  $input escaped      
   */
  function escape($input){
  
    if($this->config()->isHtml() && $this->config()->outEscape()){
      $input = htmlspecialchars($input,ENT_NOQUOTES);
    }//if
    
    return $input;
  
  }//method
  
  /**
   *  takes a full path and returns the path from the server's public web root
   *  
   *  @param  string  $path the original path that should be slimmed            
   *  @return string  the slimmed down path from the web root, not the server root
   */        
  function path($path){
    
    // sanity...
    if(empty($path)){ return ''; }//if
    
    $file_path = explode(DIRECTORY_SEPARATOR,$path);
    $doc_root = !empty($_SERVER['DOCUMENT_ROOT'])
      ? $_SERVER['DOCUMENT_ROOT']
      : (!empty($_ENV['DOCUMENT_ROOT']) ? $_ENV['DOCUMENT_ROOT'] : getcwd());

    // we use a preg with both directory separators because apache will always return /, but we wan't to be safe...
    $root_path = empty($doc_root) ? array() : explode('/',str_replace('\\','/',$doc_root));
    $slimmed_path = array_diff($file_path,$root_path);

    return DIRECTORY_SEPARATOR.implode(DIRECTORY_SEPARATOR,$slimmed_path);
    
  }//method
  
  /**
   *  wrap the given $input in the html $tag
   *  
   *  @param  string|array  $tag  eg, span, div, b, or an array($start,$stop) (eg, array('(',')'))
   *  @param  string  $input  what will be wrapped by tag
   *  @param  string  $style  either a hex color (eg, #000000) or a list of styles for the tags style="" attribute
   *  @return string  $input wrapped in $tag with $style if output is html
   */
  function wrap($tag,$input,$style = ''){
    
    if(!empty($tag)){
    
      if(preg_match('/^#\d{6}$/u',$style)){ $style = 'color:'.$style.';'; }//if
    
      if(is_array($tag)){
      
        $input = sprintf('%s%s%s',$tag[0],$input,$tag[1]);
      }else{
      
        // you can only wrap an html tag if output is html...
        if($this->config()->isHtml()){
          $input = sprintf('<%s style="%s">%s</%s>',$tag,$style,$input,$tag);
        }//if
        
      }//if/else
      
    }//if
    
    return $input;
  
  }//method
  
  /**
   *  nicely formats integers into bytes, Kilobytes and megabytes
   *  
   *  @param  integer $bytes  the original bytes
   *  @return string  the formatted byte string         
   */
  function bytes($bytes){
  
    $line_bits = array();
    $line_bits[] = sprintf('%s bytes',number_format((float)$bytes,0,'.',','));
    $line_bits[] = $this->wrap('span',sprintf('%.2f KB',$bytes / 1024),out_config::COLOR_MODIFIER);
    $line_bits[] = $this->wrap('span',sprintf('%.2f MB',$bytes / 1048576),out_config::COLOR_PARAM);
    return join("\t\t",$line_bits);
  
  }//method


}//class

class out_file extends out_config_base {

  function __construct($path = '',$line = '',$class = '',$function = '',$function_args = array()){
  
    $this->path($path);
    $this->line($line);
    $this->className($class);
    $this->funcName($function);
    $this->funcArgs($function_args);
  
  }//method

  function line($val = null){ return $this->val('out_file::line',$val,''); }//method
  function hasLine(){ return $this->has('out_file::line'); }//method
  
  function path($val = null){ return $this->val('out_file::path',$val,''); }//method
  function hasPath(){ return $this->has('out_file::path'); }//method
  
  function className($val = null){ return $this->val('out_file::class',$val,''); }//method
  function hasClassName(){ return $this->has('out_file::class'); }//method
  function funcName($val = null){ return $this->val('out_file::function',$val,''); }//method
  function hasFuncName(){ return $this->has('out_file::function'); }//method
  function funcArgs($val = null){ return $this->val('out_file::function_args',$val,''); }//method
  function hasFuncArgs(){ return $this->has('out_file::function_args'); }//method
  
  function getMethod(){
  
    $method_call = '';
    if(!$this->hasClassName()){
    
      if($this->hasFuncName()){ $method_call = $this->funcName(); }//if
    
    }else{
      
      if(!$this->hasFuncName()){
      
        $method_call = $this->className();
      
      }else{
      
        $class = $this->className();
        $method = $this->funcName();
      
        $method_reflect = new ReflectionMethod($class,$method);
        $method_modifiers = join(' ',Reflection::getModifierNames($method_reflect->getModifiers()));
        
        $method_caller = '->';
        if(mb_stripos($method_modifiers,'static') !== false){
          $method_caller = '::';
        }//if
        
        $method_call = sprintf(
          '%s %s%s%s',
          $method_modifiers,
          $class,
          $method_caller,
          $method
        );
        
      }//if/else
      
    }//if/else
    
    $method_args = '';
    
    if($this->hasFuncArgs()){
    
      $arg_list = array();
    
      foreach($this->funcArgs() as $arg){
        
        if(is_object($arg)){
          $arg_list[] = get_class($arg);
        }else{
          if(is_array($arg)){
            $arg_list[] = sprintf('Array(%s)',count($arg));
          }else if(is_bool($arg)){
            $arg_list[] = $arg ? 'TRUE' : 'FALSE';
          }else if(is_null($arg)){
            $arg_list[] = 'NULL';
          }else if(is_string($arg)){
            $arg_list[] = sprintf('"%s"',$arg);
          }else{
            $arg_list[] = $arg;
          }//if/else
        }//if/else
      
      }//foreach
      
      $method_args = join(', ',$arg_list);
      
    }//if
    
    return sprintf('%s(%s)',$method_call,$method_args);
    
  }//method
  
  /**
   *  return the path information that is contained in this file instance
   *  
   *  @param  boolean $wrap_in_parens true to wrap the information in parenthesis
   *  @param  boolean $include_method true to include class::function() after the path and line
   *  @return string  the file info nicely formatted
   */
  function out($wrap_in_parens = false,$include_method = false){
    
    $ret_str = '';
    
    $format_handler = new out_format($this->config());
    
    if($this->hasPath()){
    
      $ret_str = $format_handler->path($this->path());
    
      if($this->hasLine()){
        $ret_str = sprintf('%s:%s',$ret_str,$this->line());
      }//if
      
      if($include_method){
        $ret_str = sprintf('%s %s',$ret_str,$this->getMethod());
      }//if
      
      $ret_str = $format_handler->wrap(
        'span',
        $wrap_in_parens ? $format_handler->wrap(array('(',')'),$ret_str) : $ret_str,
        out_config::COLOR_PATH
      );
      
    }//if
    
    return $ret_str;
    
  }//method
  function __toString(){ return $this->out(); }//method

}//class

class out_config extends out_base {

  /**
   *  the HTML color of a filepath
   */     
  const COLOR_PATH = '#000099';
  
  /**
   *  the HTML color of the quotes for an enquoted value
   */
  const COLOR_ENQUOTE = '#000099';
  
  /**
   *  the HTML color of an array index
   */
  const COLOR_INDEX = '#990000';
  
  /**
   *  the HTML color of a property/method modifier (static, private)
   */
  const COLOR_MODIFIER = '#800000';
  
  /**
   *  the HTML color of a parameter ($var)
   */
  const COLOR_PARAM = '#000080';
  
  /**
   *  the HTML color of a type (eg boolean, string)
   */
  const COLOR_TYPE = '#800080';

  /**#@+
   *  output types
   *  @var  integer   
   */
  /**
   *  for outputting stuff in html
   */        
  const OUT_HTML = 1;
  /**
   *  for outputting stuff in plain text
   */     
  const OUT_TXT = 2;
  /**#@-*/
  
  /**
   *  return true if $type is html, otherwise false
   *  
   *  @param  integer $type the type that output should be      
   */     
  function isHtml(){
    return ($this->outType() === self::OUT_HTML);
  }//method

  /**
   *  return true if in CLI
   *  
   *  @since  10-12-10   
   *  @return boolean
   */
  public function isCli(){ return (strncasecmp(PHP_SAPI, 'cli', 3) === 0); }//method
  
  /**
   *  return true if in AJAX
   *  
   *  @since  2-21-11 
   *  @return boolean
   */
  public function isAjax(){
    // canary...
    if(empty($_SERVER['HTTP_X_REQUESTED_WITH'])){ return false; }//if
    return (mb_stripos($_SERVER['HTTP_X_REQUESTED_WITH'],'XMLHttpRequest') !== false);
  }//method

  /**
   *  get/set the output type, this allows plain text output for certain things but
   *  rich text (eg, html) for other things   
   *  
   *  @param  integer one of the OUT_* constants
   */
  function outType($val = null){
  
    if($val !== null){
    
      // set the out type if it hasn't been explicitely set...
      if($val < 1){
        
        // if we're on the command line or in ajax we want to default to plain text...
        if($this->isCli() || $this->isAjax()){
          $val = self::OUT_TXT;
        }else{
          $val = self::OUT_HTML;
        }//if/else
        
      }//if
    
    }//if/else
    
    return $this->val(__METHOD__,$val,0);
  
  }//method
  
  /**
   *  get/set whether if arg value is an object it should be output
   *  
   *  @param  boolean      
   */
  function outObject($val = null){ return $this->val(__METHOD__,$val,false); }//method
  
  /**
   *  get/set whether arg value should be quoted (ie put " on each end)
   *  
   *  @param  boolean      
   */
  function outEnquote($val = null){ return $this->val(__METHOD__,$val,false); }//method
  
  /**
   *  get/set whether arg value should be escaped on output
   *  
   *  @param  boolean      
   */
  function outEscape($val = null){ return $this->val(__METHOD__,$val,true); }//method
  
  /**
   *  get/set whether info should be outputted for an arg instead of value  
   *
   *  set to true if you want the out_arg instance to print info about the arg instead of the value
   *  
   *  @param  boolean      
   */
  function outInfo($val = null){ return $this->val(__METHOD__,$val,false); }//method
  
  /**
   *  get/set whether character info (octal dump) should be outputted for an arg instead of value  
   *
   *  set to true if you want the out_arg instance to print info about the arg instead of the value
   *  
   *  @param  boolean      
   */
  function outChar($val = null){ return $this->val(__METHOD__,$val,false); }//method
  
  /**
   *  get/set whether memory will be outputted for arg instead of value  
   *
   *  set to true if you want the out_arg instance to print info about the arg instead of the value
   *  
   *  @param  boolean
   */
  function outMem($val = null){ return $this->val(__METHOD__,$val,false); }//method


}//class
