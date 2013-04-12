<?php
/**
 * Doofinder On-site Search Prestashop Module
 *
 * Author:  Carlos Escribano <carlos@markhaus.com>
 * Website: http://www.doofinder.com / http://www.markhaus.com
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to
 * deal in the Software without restriction, including without limitation the
 * rights to use, copy, modify, merge, publish and distribute copies of the
 * Software, and to permit persons to whom the Software is furnished to do so,
 * subject to the following conditions:
 *
 *     - The above copyright notice and this permission notice shall be
 *       included in all copies or substantial portions of the Software.
 *     - The Software shall be used for Good, not Evil.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS
 * IN THE SOFTWARE.
 *
 * This Software is licensed with a Creative Commons Attribution NonCommercial
 * ShareAlike 3.0 Unported license:
 *
 *       http://creativecommons.org/licenses/by-nc-sa/3.0/
 */

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
