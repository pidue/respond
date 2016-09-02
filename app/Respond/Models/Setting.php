<?php

namespace App\Respond\Models;

use App\Respond\Libraries\Utilities;
use App\Respond\Libraries\Publish;

use App\Respond\Models\Site;
use App\Respond\Models\User;

// DOM parser
use Sunra\PhpSimple\HtmlDomParser;

/**
 * Models setting
 */
class Setting {

  public $id;
  public $label;
  public $description;
  public $type;
  public $value;

  /**
   * Constructs a page from an array of data
   *
   * @param {arr} $data
   */
  function __construct(array $data) {
    foreach($data as $key => $val) {
      if(property_exists(__CLASS__,$key)) {
        $this->$key = $val;
      }
    }
  }


  /**
   * Gets a setting for a given $id
   *
   * @param {string} $id
   * @return {string}
   */
  public static function getById($id, $siteId) {

    $file = app()->basePath().'/resources/sites/'.$siteId.'/settings.json';

    $settings = json_decode(file_get_contents($file), true);

    // get setting by id
    foreach($settings as $setting) {

      if($setting['id'] === $id) {

        return $setting['value'];

      }

    }

    return NULL;

  }



  /**
   * lists all settings
   *
   * @param {files} $data
   * @return {array}
   */
  public static function listAll($siteId) {

    $file = app()->basePath().'/resources/sites/'.$siteId.'/settings.json';

    return json_decode(file_get_contents($file), true);

  }

  /**
   * Saves all settings
   *
   * @param {string} $name
   * @param {string} $siteId site id
   * @return Response
   */
  public static function saveAll($settings, $user, $site) {

    // get file
    $file = app()->basePath().'/resources/sites/'.$site->id.'/settings.json';

    // get settings
    if(file_exists($file)) {

      file_put_contents($file, json_encode($settings, JSON_PRETTY_PRINT));

      // update settings in the pages
      $arr = Page::listAll($user, $site);

      foreach($arr as $item) {

        // get page
        $page = new Page($item);

        $path = app()->basePath().'/public/sites/'.$site->id.'/'.$page->url.'.html';

        // fix double html
        $path = str_replace('.html.html', '.html', $path);

        // init css
        $set_css = false;
        $css = '';

        if(file_exists($path)) {

          // get contents of the page
          $html = file_get_contents($path);

          // parse HTML
          $dom = HtmlDomParser::str_get_html($html, $lowercase=true, $forceTagsClosed=false, $target_charset=DEFAULT_TARGET_CHARSET, $stripRN=false, $defaultBRText=DEFAULT_BR_TEXT, $defaultSpanText=DEFAULT_SPAN_TEXT);

          // walk through settings
          foreach($settings as $setting) {


            if (isset($setting['actions']) && is_array($setting['actions'])) {
              // multiple action for the current setting
              foreach($setting['actions'] as $action) {
                $merged = array_merge((Array)$action, (Array)$setting);
                if (isset($merged['sets'])) {
                  self::applySetting($merged, $dom, $set_css, $css);
                }
              }
            } else if (isset($setting['sets'])) {
              self::applySetting($setting, $dom, $set_css, $css);
            }            

          }

          // remove existing inline styles
          $styles = $dom->find('[respond-settings]');

          foreach($styles as $style) {
             $style->outertext = '';
          }

          // append style to the dom
          $head = $dom->find('head', 0);

          if($head != NULL) {
            $head->innertext = $head->innertext() . '<style respond-settings>'.$css.'</style>';
          }

          // update contents
          file_put_contents($path, $dom);

        }

      }

      return TRUE;


    }

    return FALSE;

  }

  private static function applySetting($setting, $dom, &$set_css, &$css) {

    $selector = isset($setting['selector']) ? $setting['selector'] : '['.$setting['id'].']';

    // set attribute
    if($setting['sets'] == 'attribute') {

      // find elements
      $els = $dom->find($selector);

      // set attribute
      foreach($els as $el) {
        $el->setAttribute($setting['attribute'], $setting['value']);
      }

    }

    // set css
    if($setting['sets'] == 'css') {

      // build css string
      $set_css = true;
      $css .= str_replace('config(--'.$setting['id'].')', $setting['value'], $setting['css']);

    }

    // set element content
    if($setting['sets'] == 'content') {

      // find elements
      $els = $dom->find($selector);

      // set attribute
      foreach($els as $el) {
        $el->innertext = isset($setting['escape']) ?  htmlentities($setting['value']) : $setting['value'];
      }

    }

    // set element content
    if($setting['sets'] == 'class') {

      // find elements
      $els = $dom->find($selector);

      // set attribute
      foreach($els as $el) {
        if ($setting['type'] == 'select') {
          $currentClasses = $el->getAttribute('class');
          foreach($setting['options'] as $option) {
            $currentClasses = self::removeClass($currentClasses, $option['value']);
          }
          $currentClasses = self::addClass($currentClasses, $setting['value']);
          $el->setAttribute('class', $currentClasses);
        } else if ($setting['type'] == 'text') {
          $el->setAttribute('class', $setting['value']);
        } else if ($setting['type'] == 'checkbox') {
          $currentClasses = $el->getAttribute('class');
          $currentClasses = self::removeClass($currentClasses, $setting['class']);
          if ($setting['value']) {
            $currentClasses = self::addClass($currentClasses, $setting['class']);
          }
          $el->setAttribute('class', $currentClasses);
        }
      }

    }
    // TODO: add / remove css class. 
    // when a checkbox setting, add class if true, remove class if false (preserving the other classes)
    // when a select setting, remove all options then add the selected one (preserving the other classes)
    // when a text setting, replace the whole 'class' attribute value


  }

  private static function removeClass($currentClasses, $classToRemove ) {
    $currentClasses = preg_replace('/(?<=\s|^)' . $classToRemove . '(?=\s|$)/','', $currentClasses);
    $currentClasses = preg_replace('/ +/',' ', $currentClasses);
    return $currentClasses;
  }

  private static function addClass($currentClasses, $classToAdd ) {
    $currentClasses = preg_replace('/$/',' ' . $classToAdd, $currentClasses);
    $currentClasses = preg_replace('/ +/',' ', $currentClasses);
    return $currentClasses;
  }
}

