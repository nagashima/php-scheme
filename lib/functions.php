<?php // -*- mode: php; coding: utf-8 -*-

// FSUBR

function SCM_begin($s, $env)
{
  $ret = null;
  while ($s)
  {
    $ret = Scheme::evaluate($s->car, $env);
    $s = $s->cdr;
  }
  return $ret;
}
$env->bind(Symbol::new_instance('begin'), FSubr::new_instance('SCM_begin'));


function SCM_lambda($s, $env)
{
  $args  = $s->car;
  $forms = $s->cdr;
  return Expr::new_instance($forms, $args, $env);
}
$env->bind(Symbol::new_instance('lambda'), FSubr::new_instance('SCM_lambda'));


// (let ((p1 v1) (p2 v2) ...) exp1 exp2 ...)
// ⇒
// ((lambda (p1 p2 ...) exp1 exp2 ...) v1 v2)

// (let ((x 3) (y 4)) (+ x y))
// ((lambda (x y) (+ x y)) 3 4)
function SCM_let($s, $env)
{
  $def   = $s->car;
  $forms = $s->cdr;

  $prov = Cons::new_instance();
  $real = Cons::new_instance();
  
  while ($def)
  {
    $prov->add_cdr($def->car->car);
    $real->add_cdr($def->car->cdr);

    $def = $def->cdr;
  }

  $prov = $prov->cdr;
  $real = $real->cdr;

  $lambda = Cons::new_instance(Symbol::new_instance('lambda'));
  $lambda->add_cdr($prov);
  $lambda->add_cdr($forms);
  var_dump($lambda);
  $cons = Cons::new_instance($lambda);
  $cons->add_cdr($real);

  return Scheme::evaluate($cons, $env);
}
$env->bind(Symbol::new_instance('let'), FSubr::new_instance('SCM_let'));


function SCM_define($s, $env)
{
  $key = $s->car;

  if (Object::is_cons($key))
  {
    $key   = $s->car->car;
    $args  = $s->car->cdr;
    $forms = $s->cdr->car;

    // ラムダに変換
    $cons = Cons::new_instance(Symbol::new_instance('lambda'));
    $cons->add_cdr($args);
    $cons->add_cdr($forms);
    $val = Scheme::evaluate($cons, $env);
  }
  else
  {
    $val = $s->cdr->car;
    $val = Scheme::evaluate($val, $env);
  }
  
  $env->bind($key, $val);  
  return $key;
}
$env->bind(Symbol::new_instance('define'), FSubr::new_instance('SCM_define'));


function SCM_set($s, $env)
{
  $key = $s->car;
  $val = $s->cdr->car;
  if ($env->get($key))
  {
    SCM_define($s, $env);
  }
  else
  {
    $env->bind($key, $val);
  }
  return $key;
}
$env->bind(Symbol::new_instance('set!'), FSubr::new_instance('SCM_set'));


function SCM_cond($s, $env)
{
  while ($s)
  {
    if (Scheme::evaluate($s->car->car, $env) != Symbol::new_instance('#f'))
    {
      return SCM_begin($s->car->cdr, $env);
    }
    $s = $s->cdr;
  }
  return null;
}
$env->bind(Symbol::new_instance('cond'), FSubr::new_instance('SCM_cond'));
$env->bind(Symbol::new_instance('else'), Symbol::new_instance('#t'));


function SCM_if($s, $env)
{
  $cond = $s->car;

  if (Scheme::evaluate($cond, $env) == Symbol::new_instance('#t'))
  {
    $rule = $s->cdr->car;
  }
  else
  {
    $rule = $s->cdr->cdr;
  }
  return SCM_begin($rule, $env);
}
$env->bind(Symbol::new_instance('if'), FSubr::new_instance('SCM_if'));



// SUBR

function SCM_display($s)
{
  if ($x = $s->car)
  {
    return $x;
  }
}
$env->bind(Symbol::new_instance('display'), Subr::new_instance('SCM_display'));


function SCM_car($s)
{
  if ($x = $s->car)
  {
    return $x->car;
  }
}
$env->bind(Symbol::new_instance('car'), Subr::new_instance('SCM_car'));


function SCM_cdr($s)
{
  if ($x = $s->car)
  {
    return $x->cdr;
  }
}
$env->bind(Symbol::new_instance('cdr'), Subr::new_instance('SCM_cdr'));


function SCM_cons($s)
{
  $x = $s->car;
  $y = $s->cdr->car;

  $cons = Cons::new_instance();
  $cons->set_car($x);
  $cons->add_cdr($y);

  return $cons;
}
$env->bind(Symbol::new_instance('cons'), Subr::new_instance('SCM_cons'));


function SCM_eq($s)
{
  $x = $s->car;
  $y = $s->cdr->car;

  if (Object::is_number($x) && Object::is_number($y) && ($x->value == $y->value))
  {
    return Symbol::new_instance('#t');
  }
  else
  {
    return Symbol::new_instance('#f');
  }
}
$env->bind(Symbol::new_instance('eq?'), Subr::new_instance('SCM_eq'));


function SCM_less_than($s)
{
  $x = $s->car;
  $y = $s->cdr->car;

  if (Object::is_number($x) && Object::is_number($y) && ($x->value < $y->value))
  {
    return Symbol::new_instance('#t');
  }
  else
  {
    return Symbol::new_instance('#f');
  }
}
$env->bind(Symbol::new_instance('<'), Subr::new_instance('SCM_less_than'));


function SCM_greater_than($s)
{
  $x = $s->car;
  $y = $s->cdr->car;

  if (Object::is_number($x) && Object::is_number($y) && ($x->value > $y->value))
  {
    return Symbol::new_instance('#t');
  }
  else
  {
    return Symbol::new_instance('#f');
  }
}
$env->bind(Symbol::new_instance('>'), Subr::new_instance('SCM_greater_than'));


function SCM_atom($s)
{
  $x = $s->car;
  
  if (!Object::is_cons($x))
  {
    return Symbol::new_instance('#t');
  }
  else
  {
    return Symbol::new_instance('#f');
  }
}
$env->bind(Symbol::new_instance('atom?'), Subr::new_instance('SCM_atom'));


function SCM_plus($s)
{
  $x = $s->car;
  $y = $s->cdr->car;

  if (Object::is_number($x) && Object::is_number($y))
  {
    return Number::new_instance($x->value + $y->value);
  }
}
$env->bind(Symbol::new_instance('+'), Subr::new_instance('SCM_plus'));


function SCM_minus($s)
{
  $x = $s->car;
  $y = $s->cdr->car;

  if (Object::is_number($x) && Object::is_number($y))
  {
    return Number::new_instance($x->value - $y->value);
  }  
}
$env->bind(Symbol::new_instance('-'), Subr::new_instance('SCM_minus'));


function SCM_multiply($s)
{
  $x = $s->car;
  $y = $s->cdr->car;

  if (Object::is_number($x) && Object::is_number($y))
  {
    return Number::new_instance($x->value * $y->value);
  }
}
$env->bind(Symbol::new_instance('*'), Subr::new_instance('SCM_multiply'));


function SCM_divide($s)
{
  $x = $s->car;
  $y = $s->cdr->car;

  if (Object::is_number($x) && Object::is_number($y))
  {
    return Number::new_instance($x->value / $y->value);
  }
}
$env->bind(Symbol::new_instance('/'), Subr::new_instance('SCM_divide'));


function SCM_abs($s)
{
  $x = $s->car;
  
  if (Object::is_number($x))
  {
    $value = ($x->value >= 0) ? $x->value : $x->value * -1;
    return Number::new_instance($value);
  }
}
$env->bind(Symbol::new_instance('abs'), Subr::new_instance('SCM_abs'));
