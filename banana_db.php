<?php

/**
 * @author LaNsHoR / http://www.lanshor.com / lanshor@gmail.com
 */

/****************************************************************************
The MIT License

Copyright © 2010-2015 BananaDB author

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.
****************************************************************************/

class BDB
{
   //Util Methods
   public static function valueArrayFromReference($reference_array, $meta=0x0)
   {
      $value_array=array();
      foreach($reference_array as $reference)
      {
         $value_array[]=$reference;
         if($meta)
         {
            $field=$meta->fetch_field();
            $value_array[$field->name]=$reference;
         }
      }
      return $value_array;
   }
   public function getLastId()       {return $this->mysqli?$this->mysqli->insert_id:false;}
   public function getAffectedRows() {return $this->mysqli?$this->mysqli->affected_rows:false;}
   //Static way
   public static function getInstance() {return BDB::$last_instance;}
   public static function init($host, $user, $password, $database)
   {
      new BDB($host, $user, $password, $database);
   }
   //Here the magic begins...
   public $mysqli=0x0;
   public $pending_exec=false;
   public static $instances=array();
   public static $last_instance=0x0;
   //========================
   //Reset the current state when the object needs to be ready for a new query
   private function reset()
   {
      if($this->pending_exec)
         trigger_error("Execution not called at BananaDB instance. Do you forget to do '->exec()'?");
      $this->values=array();
      $this->chain=array();
      $this->values=array();
      $this->query_type="";
      $this->query_string="";
      $this->pending_exec=false;
   }
   //========================
   private function connect($host, $user, $password, $database)
   {
      if(!$this->mysqli)
      {
         $this->mysqli=new mysqli($host, $user, $password, $database);
         if($this->mysqli->connect_errno)
            throw new Exception("BananaDB - Connection Error [".$this->mysqli->connect_errno."]: ".$this->mysqli->connect_error);
         $this->mysqli->set_charset("utf8");
         BDB::$last_instance=$this;
      }
   }
   public function __construct($host, $user, $password, $database)
   {
      $this->connect($host, $user, $password, $database);
      $this->reset();
      BDB::$instances[]=$this;
   }
   //========================
   private function lan_select($field_list)
   {
      $this->reset();
      $this->pending_exec=true;
      $this->query_type="select";
      $this->chain[]="select";
      foreach($field_list as $index => $field)
         $this->chain[]=($index?", ":" ").$field;
   }
   //========================
   private function lan_from($table_list)
   {
      $this->chain[]=" from ";
      foreach($table_list as $index => $table)
         $this->chain[]=($index?", ":" ").$table;
   }
   //========================
   private function lan_condition($op, $condition, $value)
   {
      if($value===NULL)
         $this->chain[]=" $op $condition";
      else
      {
         $this->chain[]=" $op $condition ?";
         $this->values[]=&$value;
      }
   }
   //========================
   private function lan_left_join($table_list)
   {
      $this->chain[]=" left join ";
      foreach($table_list as $index => $table)
         $this->chain[]=($index?", ":" ").$table;
   }
   private function lan_right_join($table_list)
   {
      $this->chain[]=" right join ";
      foreach($table_list as $index => $table)
         $this->chain[]=($index?", ":" ").$table;
   }
   //========================
   private function lan_where($condition, $value=NULL) {$this->lan_condition("where", $condition, $value);}
   private function lan_and($condition, $value=NULL)   {$this->lan_condition("and", $condition, $value);}
   private function lan_or($condition, $value=NULL)    {$this->lan_condition("or", $condition, $value);}
   private function lan_on($condition, $value=NULL)    {$this->lan_condition("on", $condition, $value);}
   //========================
   private function lan_delete($value)
   {  	
      $this->reset();
      $this->pending_exec=true;
      $this->query_type="delete";
      $this->chain[]="delete from $value";
   }
   //========================
   private function lan_insert($table)
   {
      $this->reset();
      $this->pending_exec=true;
      $this->query_type="insert";
      $this->chain[]="insert into $table";
   }
   //========================
   private function lan_duplicate_key($sets_array)
   {
      $this->chain[] = " on duplicate key update ";
      $this->set_values($sets_array);
   }
   //========================
   private function lan_update($table)
   {
      $this->reset();
      $this->pending_exec=true;
      $this->query_type="update";
      $this->chain[]="update $table ";
   }
   //========================
   private function lan_group_by($value) {$this->chain[]=" group by $value";}
   //========================
   private function lan_having($condition, $value=NULL) {$this->lan_condition("having", $condition, $value);}
   //========================
   private function lan_limit($from, $to=NULL)
   {
   	  $all_together=explode(",", $from);
   	  if(!$to && count($all_together==2))
   	  {
   	  	  $from=$all_together[0];
   	  	  $to=$all_together[1];
   	  }
   	  $limit_string=" limit ".((int)$from);
      if($to)
	      $limit_string.=",".((int)$to);
	  $this->chain[]=$limit_string;
   }
   //========================
   private function lan_offset($offset) {$this->chain[]=" offset ".((int)$offset);}
   //========================
   private function lan_order_by($field_list)
   {
      $this->chain[]=" order by ";
      foreach($field_list as $index => $value)
      {
         if($index>0)
            $this->chain[]=", ";
         $this->chain[]=" $value ";
      }
   }
   //========================
   private function lan_values($values_array)
   {
   	  $values_string="";
   	  //Custom columns support (["column1", "value1"], ["column2", "value2"])
   	  if(is_array($values_array[0]))
   	  {
   	  	 $values_string.="(";
   	     foreach($values_array as $index => $field_pair)
   	     {
   	     	if($index)
   	     		$values_string.=", ";	
   	     	$values_string.=$field_pair[0];
   	     }
   	     $values_string.=")";
   	  }
      $values_string.=" values(";
      foreach($values_array as $index => $value)
      {
      	 if(is_array($value)) //Custom columns ["column", "value"]
      	 	$value=$value[1];
         if(is_object($value))
            $this->values[]=&$value->ref_value;
         else
         {
            if($value[0]=="!") //Literal parameter
            {
               if($index)
                  $values_string.=",";
               $values_string.=substr($value,1); //Removes ! from literal paramater
               continue;
            }
            if($value[0]=="\\")
               $value=substr($value,1); //Removes \ (escape)
            $this->values[]=trim($value);
         }
         if($index)
            $values_string.=",";
         $values_string.="?";
      }
      $values_string.=")";
      $this->chain[]=$values_string;
   }
   //========================
   private function lan_set($sets_array)
   {
      $this->chain[]="set ";
      $this->set_values($sets_array);
   }
   //========================
   private function set_values($sets_array) //update values from 'set', 'on duplicate key'...
   {
      //Format 1: set("field = literal")
      if(!is_array($sets_array[0]) && !isset($sets_array[1]))
      {
         $this->chain[]=$sets_array[0];
         return;
      }
      //Format 2: set("field = ", x)
      if(!is_array($sets_array[0]))
      {
         //Case 2A: Literal parameter
         if($sets_array[1][0]=="!")
         {
            $this->chain[]=$sets_array[0].substr($sets_array[1],1); //Removes ! from literal paramater
            return;
         }
         //Case 2B: Variable parameter
         $this->chain[]=$sets_array[0]."?";
         $this->values[]=$sets_array[1];
         return;
      }
      //Format 3: set( ["field1 =",x], ["field2=",y], ... );
      foreach($sets_array as $index => $pair)
      {
         $field=$pair[0];
         $value=$pair[1];
         if($index)
            $this->chain[]=",";
         //Case 3A: Literal parameter
         if($value[0]=="!")
         {
            $this->chain[]=$field.substr($value,1); //Removes ! from literal parameter
            continue;
         }
         //Case 3B: Variable parameter
         $this->chain[]=$field."?";
         $this->values[]=$value;
      }
   }
   //========================
   private function commonExecutionSteps()
   {
      //form the query string
      $query_string="";
      foreach($this->chain as $fragment)
         $query_string.=$fragment;
      //prepare statement
      $query=$this->mysqli->prepare($query_string);
      //bind params
      $params_string="";
      $params_list=array(&$params_string);
      foreach($this->values as &$value)
      {
         if(is_string($value))
            $params_string.="s";
         elseif(is_integer($value))
            $params_string.="i";
         elseif(is_double($value))
            $params_string.="d";
         $params_list[]=$value;
      }
      //Error control
      if(!$query)
         throw new Exception("BananaDB - Parsing Error [".$query_string."]: ".$this->mysqli->error);
      if(!empty($this->values))
      {
         $reflexive_query=new ReflectionClass("mysqli_stmt");
         $method=$reflexive_query->getMethod("bind_param");
         $method->invokeArgs($query,$params_list);
      }
      //execute
      $query->execute();
      return $query;
   }
   public function exec()
   {
      $this->pending_exec = false;
      $query=$this->commonExecutionSteps();
      if($this->query_type=="select")
      {
         //result
         $return_list=array();
         $meta=$query->result_metadata();
         while($field=$meta->fetch_field())
            $return_list[]=&$row[$field->name];
         $reflexive_query=new ReflectionClass("mysqli_stmt");
         $method=$reflexive_query->getMethod("bind_result");
         $method->invokeArgs($query,$return_list);
         $total_result=array();
         while($line=$query->fetch())
            $total_result[]=BDB::valueArrayFromReference($return_list, $query->result_metadata());
         if($this->mysqli->error)
      	 	throw new Exception("BananaDB - MySQL Error [".$this->mysqli->error."]");
         $query->close();
         return $total_result;
      }
      if($this->query_type=="insert" || $this->query_type=="update" || $this->query_type=="delete")
      {
      	 if($this->mysqli->error)
      	 	throw new Exception("BananaDB - MySQL Error [".$this->mysqli->error."]");
         $query->close();
         return true;
      }
   }
   public function exec_one_row()
   {
      $result=$this->exec();
      return $result ? $result[0] : false;
   }
   public function exec_one_line()
   {
      trigger_error('BananaDB Warning: exec_one_line is deprecated. Please, use exec_one_row instead.');
      return $this->exec_one_row();
   }
   public function exec_one_field()
   {
      $result=$this->exec();
      if(!$result || !$result[0])
         return false;
      return $result[0][0];
   }
   //========================
   function __call($function, $args)
   {
      $function=strtolower($function);
      switch($function)
      {
         case 'select':
            $this->lan_select($args);
            return $this;
         case 'from':
            $this->lan_from($args);
            return $this;
         case 'left_join':
            $this->lan_left_join($args);
            return $this;
         case 'right_join':
            $this->lan_right_join($args);
            return $this;
         case 'where': //where("x >", $x) or where("x = x")
            if(count($args)>1)
               $this->lan_where($args[0], $args[1]);
            else
               $this->lan_where($args[0]);
            return $this;
         case 'and':
            if(count($args)>1)
               $this->lan_and($args[0], $args[1]);
            else
               $this->lan_and($args[0]);
            return $this;
         case 'or':
            if(count($args)>1)
               $this->lan_or($args[0], $args[1]);
            else
               $this->lan_or($args[0]);
            return $this;
         case 'on':
            if(count($args)>1)
               $this->lan_on($args[0], $args[1]);
            else
               $this->lan_on($args[0]);
            return $this;
         case 'order_by':
            $this->lan_order_by($args);
            return $this;
         case 'insert_into':
            $this->lan_insert($args[0]);
            return $this;
         case 'on_duplicate_key_update':
            $this->lan_duplicate_key($args);
            return $this;
         case 'values':
            $this->lan_values($args);
            return $this;
         case 'update':
            $this->lan_update($args[0]);
            return $this;
         case 'set':
            $this->lan_set($args);
            return $this;
         case 'delete_from':
            $this->lan_delete($args[0]);
            return $this;
         case 'group_by':
            $this->lan_group_by($args[0]);
            return $this;
         case 'having':
            if(count($args)>1)
               $this->lan_having($args[0], $args[1]);
            else
               $this->lan_having($args[0]);
            return $this;
         case 'limit':
         	if(count($args)>1)
               $this->lan_limit($args[0], $args[1]);
            else
               $this->lan_limit($args[0]);
            return $this;
         case 'offset':
         	$this->lan_offset($args[0]);
         	return $this;
         //Unknown operation
         throw new Exception("BananaDB - Unknown Operation [$function]");
      }
   }
}

class_alias("BDB", "BananaDB");

register_shutdown_function(function()
{
   foreach(BDB::$instances as $instance)
   {
      if($instance->pending_exec)
         trigger_error("Execution not called at BananaDB instance. Do you forget to do '->exec()'?");
   }
});
