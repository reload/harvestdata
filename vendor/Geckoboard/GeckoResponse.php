<?php
/**
 * Class GeckoResponse - for response to geckoboard API
 */
class GeckoResponse {
    /**
     * Response format - xml or json
     */
    private $format = 'xml';

    /**
     * @var string root directory
     */
    protected static $_path;

     /**
      * simple autoload function
      * returns true if the class was loaded, otherwise false
      *
      * <code>
      * // register the class auto loader 
 	 * spl_autoload_register( array('GeckoResponse', 'autoload') );
      * </code>
      * 
      * @param string $classname Name of Class to be loaded
      * @return boolean
      */
     public static function autoload($className)
     {
         if (class_exists($className, false) || interface_exists($className, false)) {
             return false;
         }
         $class = self::getPath() . DIRECTORY_SEPARATOR . str_replace('_', DIRECTORY_SEPARATOR, $className) . '.php';
         if (file_exists($class)) {
             require $class;
             return true;
         }
         return false;
     }

     /**
      * Get the root path to class
      *
      * @return String
      */
     public static function getPath()
     {
         if ( ! self::$_path) {
             self::$_path = dirname(__FILE__);
         }
         return self::$_path;
     }


    /**
     * Set response format
     *
     * @access public
     * @param string $format xml or json
     * @return void
     */
    public function setFormat($format) {
        $this->format = $format;
    }

    /**
     * Create response string
     *
     * @access public
     * @param array $data
     * @param bool $cdata
     * @return string
     */
    public function getResponse($data, $cdata = false) {
        switch ($this->format) {
            case 'xml':
                $response = $this->getXmlResponse($data, $cdata);
                break;
            case 'json':
                $response = $this->getJsonResponse($data);
                break;
        }
        return $response;
    }

    /**
     * Create response in xml format
     *
     * @access private
     * @param array $data
     * @param bool $cdata
     * @return string
     */
    private function getXmlResponse($data, $cdata) {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $root = $dom->createElement('root');
        $dom->appendChild($root);

        foreach ($data as $k => $v) {
            if (is_array($v)) {
                for ($i = 0; $i < sizeof($v); $i++) {
                    $item = $dom->createElement($k);
                    foreach ($v[$i] as $k1 => $v1) {
                        $cdata = $dom->createCDATASection($v1);
                        $node = $dom->createElement($k1);
                        $node->appendChild($cdata);
                        $item->appendChild($node);
                    }
                    $root->appendChild($item);
                }
            }
            else {
                $item = $dom->createElement($k);
                $cdata = $dom->createCDATASection($v);
                //$node->appendChild($cdata);
                $item->appendChild($cdata);
                $root->appendChild($item);
            }
        }

        $response = $dom->saveXML();
        return $response;
    }

    /**
     * Create response in json format
     *
     * @access private
     * @param array $data
     * @return string
     */
    private function getJsonResponse($data) {
        $response = json_encode($data);
        return $response;
    }
}
