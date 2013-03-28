<?php
class dfForm
{
  public static function formAction($currentIndex, $moduleName, $token)
  {
    return "$currentIndex&configure=$moduleName&submit$moduleName=1&token=$token";
  }

  public static function fieldset($legend = false)
  {
    $html = '<fieldset>';
    if ($legend)
      $html .= "<legend>$legend</legend>";

    return $html;
  }

  public static function getSelectFor($optname, $value, $choices, $attributes=array())
  {
    $options = array();

    foreach ($choices as $optValue => $optLabel)
    {
      $selected = ($value == $optValue) ? ' selected="selected"' : '';
      $options[] = '<option value="'.$optValue.'"'.$selected.'>'.$optLabel.'</option>';
    }
    $options = implode('', $options);

    $attrs = self::htmlAttributes($attributes);
    if (!empty($attrs))
      $attrs = " $attrs";

    return '<select name="'.$optname.'" id="'.$optname.'"'.$attrs.'>'.$options.'</select>';
  }

  public static function getInputFor($optname, $value, $attributes=array())
  {
    $attrs = self::htmlAttributes($attributes);
    if (!empty($attrs))
      $attrs = " $attrs";

    return '<input name="'.$optname.'" id="'.$optname.'" value="'.$value.'"'.$attrs.' />';
  }

  public static function getTextareaFor($optname, $value, $attributes=array())
  {
    $attrs = self::htmlAttributes($attributes);
    if (!empty($attrs))
      $attrs = " $attrs";

    return '<textarea name="'.$optname.'" id="'.$optname.'"'.$attrs.'>'.$value.'</textarea>';
  }

  public static function wrapField($optname, $label, $fieldHtml, $options=array())
  {
    $html  = '<label for="'.$optname.'">'.$label.'</label>';
    $html .= '<div id="'.$optname.'_wrap" class="margin-form">';
    $html .= $fieldHtml;
    $html .= '<div class="clear"></div>';
    if (isset($options['desc']) && !empty($options['desc']))
      $html .= '<p class="desc">'.$options['desc'].'</p>';
    $html .= '</div>';
    return $html;
  }

  public static function submitButton($moduleName, $label)
  {
    $html  = '<div class="submit">';
    $html .= '<input type="submit" name="submit'.$moduleName.'" class="button" value="'.$label.'"/>';
    $html .= '</div>';

    return $html;
  }

  public static function htmlAttributes($attributes=array())
  {
    $attrs = array();

    foreach ($attributes as $name => $value)
    {
      $attrs[] = $name.'="'.$value.'"';
    }

    return implode(' ', $attrs);
  }
}
