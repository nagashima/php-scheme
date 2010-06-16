<?php // -*- mode: php; coding: utf-8 -*-

/**
 * 環境
 */
class Env
{
  public static function new_instance($parent = null)
  {
    return new Env($parent);
  }

  public static function new_expr_instance($args, $expr)
  {
    $env = self::new_instance($expr->env);
    
    $prov = $expr->args; // 仮引数
    $real = $args;       // 実引数

    while ($prov)
    {
      $env->bind($prov->car, $real->car);
      $prov = $prov->cdr;
      $real = $real->cdr;
    }

    return $env;
  }
  
  public $parent;
  public $binds;
  
  private function __construct($parent = null)
  {
    $this->parent = $parent;

    $this->binds = array();
  }

  public function bind($key, $value)
  {
    $this->binds[$key->name] = $value;
  }
  
  public function find($key)
  {
    for ($env = $this; $env != null; $env = $env->parent)
    {
      if ($value = $env->get($key))
      {
        return $value;
      }
    }
  }

  public function get($key)
  {
    if (isset($this->binds[$key->name]))
    {
      return $this->binds[$key->name];
    }
  }
}

/**
 * オブジェクト
 */
class Object
{
  const TYPE_NUMBER = 'number';
  const TYPE_STRING = 'string';
  const TYPE_SYMBOL = 'symbol';
  const TYPE_CONS   = 'cons';
  const TYPE_SUBR   = 'subr';
  const TYPE_FSUBR  = 'fsubr';
  const TYPE_EXPR   = 'expr';
  
  public $type;

  public static function is_number($object)
  {
    return ($object->type == self::TYPE_NUMBER);
  }

  public static function is_string($object)
  {
    return ($object->type == self::TYPE_STRING);
  }

  public static function is_symbol($object)
  {
    return ($object->type == self::TYPE_SYMBOL);
  }

  public static function is_cons($object)
  {
    return ($object->type == self::TYPE_CONS);
  }

  public static function is_subr($object)
  {
    return ($object->type == self::TYPE_SUBR);
  }

  public static function is_fsubr($object)
  {
    return ($object->type == self::TYPE_FSUBR);
  }

  public static function is_expr($object)
  {
    return ($object->type == self::TYPE_EXPR);
  }
}

/**
 * 数字
 */
class Number extends Object
{
  public static function new_instance($value)
  {        
    return new Number($value);
  }
  
  public $value;
  
  private function __construct($value)
  {
    $this->type = self::TYPE_NUMBER;   
    $this->value = $value;
  }

  public function __toString()
  {
    return (string)$this->value;
  }
}

/**
 * 文字列
 */
class String extends Object
{
  public static function new_instance($value)
  {        
    return new String($value);
  }
  
  public $value;
  
  private function __construct($value)
  {
    $this->type = self::TYPE_STRING;
    $this->value = trim($value, '"');
  }

  public function __toString()
  {
    return $this->value;
  }
}

/**
 * シンボル
 */
class Symbol extends Object
{
  private static $symbols;
  
  public static function new_instance($name)
  {
    if (!is_array($symbols)) self::$symbols = array();

    foreach (self::$symbols as $symbol)
    {
      if ($symbol->name == $name)
      {
        return $symbol;
      }
    }

    $symbol = new Symbol($name);
    
    self::$symbols[] = $symbol;

    return $symbol;
  }

  private function __construct($name)
  {
    $this->type = self::TYPE_SYMBOL;
    $this->name = $name;
  }

  public function __toString()
  {
    return $this->name;
  }
}

/**
 * コンスセル
 */
class Cons extends Object
{
  public static function new_instance($object = null)
  {
    return new Cons($object);
  }
  
  public $car;
  public $cdr;
  
  private function __construct($object)
  {
    $this->type = self::TYPE_CONS;   
    $this->car  = $object;
    $this->cdr  = null;
  }

  /**
   * cdr追加
   */
  public function add_cdr($object)
  {
    $cons =& $this->cdr;
    
    while (true)
    {
      if (!$cons)
      {
        $cons = Cons::new_instance($object);
        break;
      }
      else
      {
        $cons =& $cons->cdr;
      }
    }
  }
}

/**
 * 関数
 */
class Func extends Object
{
  public $function;

  public function exec($args, $env = null)
  {
    if ($this->function && function_exists($this->function))
    {
      return call_user_func($this->function, $args, $env);
    }
  }
}

/**
 * SUBR（組込み関数、引数評価あり）
 */
class Subr extends Func
{
  public static function new_instance($function)
  {
    return new Subr($function);
  }

  private function __construct($function)
  {
    $this->type     = self::TYPE_SUBR;
    $this->function = $function;
  }
}

/**
 * FSUBR（組込み関数、引数評価なし）
 */
class FSubr extends Func
{
  public static function new_instance($function)
  {
    return new FSubr($function);
  }

  private function __construct($function)
  {
    $this->type     = self::TYPE_FSUBR;
    $this->function = $function;
  }
}

/**
 * EXPR（関数、引数固定）
 */
class Expr extends Object
{
  public static function new_instance($forms, $args, $env)
  {
    return new Expr($forms, $args, $env);
  }

  public $forms;
  public $args;
  public $env;
  
  private function __construct($forms, $args, $env)
  {
    $this->type  = self::TYPE_EXPR;
    $this->forms = $forms;
    $this->args  = $args;
    $this->env   = $env;
  }
}

/**
 * Scheme処理
 */
class Scheme
{
  /**
   * 実行
   */
  public static function run($src)
  {
    if ($tokens = self::tokenize($src))
    {
      $expr = self::parse_object($tokens);

      $env = self::init_environment();

      $ret = self::evaluate($expr, $env);
      
      self::display($src, $ret);
    }
  }

  /**
   * 結果表示
   */
  private static function display($src, $ret)
  {
    $pre = "scm: ";
    
    $out = $pre . '> ' . preg_replace("/\n/", "\n".$pre."> ", trim($src))."\n";
    $out.= $pre.$ret."\n";
    
    echo $out;
  }

  /**
   * トークン解析
   */
  private static function tokenize($src)
  {
    $src = "(begin ".$src.")";
    
    if (preg_match_all('/\(|\)|[^\(\)\t\r\n ]+/', $src, $tokens))
    {
      return $tokens[0];
    }
  }

  /**
   * 要素解析
   */
  private static function parse_object(&$tokens)
  {
    $token = array_shift($tokens);

    if ($token == '(')
    {
      return self::parse_list($tokens);
    }
    elseif (is_numeric($token))
    {
      return Number::new_instance($token);
    }
    elseif (preg_match('/^".*"$/', $token))
    {
      return String::new_instance($token);
    }
    else
    {
      return Symbol::new_instance($token);
    }
  }

  /**
   * リスト解析
   */
  private static function parse_list(&$tokens)
  {
    $cons = null;

    while (list($key, $token) = each($tokens))
    {
      if (isset($token))
      {
        if ($token == ')')
        {
          array_shift($tokens);
          
          return $cons;
        }
        else
        {
          if (!$cons)
          {
            $cons = Cons::new_instance(self::parse_object($tokens));
          }
          else
          {
            $cons->add_cdr(self::parse_object($tokens));
          }
        }
      }
    }
  }

  /**
   * 環境生成
   */
  private static function init_environment()
  {
    $env = Env::new_instance();

    include_once 'functions.php';

    return $env;
  }
  
  /**
   * 評価
   */
  public static function evaluate($expr, $env)
  {
    if (Object::is_number($expr) || Object::is_string($expr))
      return $expr;

    if (Object::is_symbol($expr))
      return $env->find($expr);

    if (Object::is_cons($expr))
    {
      return self::apply(self::evaluate($expr->car, $env), $expr->cdr, $env);
    }
  }

  /**
   * 適応
   */
  private static function apply($function, $args, $env)
  {
    if (Object::is_subr($function) || Object::is_expr($function))
    {
      $args = self::evlis($args, $env);      
    }

    // SUBR
    if (Object::is_subr($function))
    {
      return $function->exec($args);
    }

    // FSUBR
    if (Object::is_fsubr($function))
    {
      return $function->exec($args, $env);
    }

    // EXPR
    if (Object::is_expr($function))
    {
      $new_env = Env::new_expr_instance($args, $function);
      return SCM_begin($function->forms, $new_env);
    }  
  }

  /**
   * リスト評価
   */
  private static function evlis($args, $env)
  {
    $proc = $args;

    $cons = Cons::new_instance();

    while ($proc)
    {
      $cons->add_cdr(self::evaluate($proc->car, $env));
      
      $proc = $proc->cdr;
    }

    return $cons->cdr;
  }
}
