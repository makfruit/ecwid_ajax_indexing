<?php

$directory = dirname(__FILE__);

class EcwidCatalog
{
    var $store_id = 0;
    var $store_base_url = '';
    var $ecwid_api = null;

    function __construct($store_id, $store_base_url)
    {
        $this->store_id = intval($store_id);
        $this->store_base_url = $store_base_url;    
        $this->ecwid_api = new EcwidProductApi($this->store_id);
    }

    function EcwidCatalog($store_id)
    {
        if(version_compare(PHP_VERSION,"5.0.0","<"))
            $this->__construct($store_id);
    }

    function get_product($id)
    {
        $params = array 
        (
            array("alias" => "p", "action" => "product", "params" => array("id" => $id)),
            array("alias" => "pf", "action" => "profile")
        );

        $batch_result = $this->ecwid_api->get_batch_request($params);
        $product = $batch_result["p"];
        $profile = $batch_result["pf"];

        $return = '';
        
        if (is_array($product)) 
        {
        
            $return = "<div itemscope itemtype=\"http://schema.org/Product\">";
            $return .= "<h1 class='ecwid_catalog_product_name' itemprop=\"name\">" . htmlentities($product["name"], ENT_COMPAT, 'UTF-8') . "</h1>";

            if (!empty($product["thumbnailUrl"]))
                $return .= "<div class='ecwid_catalog_product_image'><img itemprop=\"image\" src='" . $product["thumbnailUrl"] . "' alt='" . htmlentities($product["sku"], ENT_COMPAT, 'UTF-8') . " " . htmlentities($product["name"], ENT_COMPAT, 'UTF-8') . "'/></div>";

            $return .= "<div class='ecwid_catalog_product_price' itemprop=\"offers\" itemscope itemtype=\"http://schema.org/Offer\">Price: <span itemprop=\"price\">" . $product["price"] . "</span>&nbsp;<span itemprop=\"priceCurrency\">" . $profile["currency"] . "</span>";
            
            if (!isset($product['quantity']) || (isset($product['quantity']) && $product['quantity'] > 0))
                $return .= "<link itemprop=\"availability\" href=\"http://schema.org/InStock\" />";

            $return .= "</div>";
            $return .= "<div class='ecwid_catalog_product_description' itemprop=\"description\">" . $product["description"] . "</div>";

            if (is_array($product["galleryImages"])) {
                foreach ($product["galleryImages"] as $galleryimage) {
                    if (empty($galleryimage["alt"]))  $galleryimage["alt"] = htmlspecialchars($product["name"]);
                    $return .= "<img src='" . $galleryimage["url"] . "' alt='" . htmlspecialchars($galleryimage["alt"]) ."' title='" . htmlspecialchars($galleryimage["alt"]) ."'><br />";                    
                }
            }

            $return .= "</div>" . PHP_EOL;
        }

        return $return;
    }

	function get_product_name($id) {

		$product = $this->ecwid_api->get_product($id);

		return $product['name'];

	}

	function get_product_description($id) {

		$product = $this->ecwid_api->get_product($id);

		$description = $product['description'];

		$description = strip_tags($description);
        $description = html_entity_decode($description);
        $description = trim($description, " \t\xA0\n\r");// Space, tab, non-breaking space, newline, carriage return
        $description = mb_substr($description, 0, 160, 'utf-8');

		return $description;

	}

    function get_category($id)
    {
        $params = array
        (
            array("alias" => "c", "action" => "categories", "params" => array("parent" => $id)),
            array("alias" => "p", "action" => "products", "params" => array("category" => $id)),
            array("alias" => "pf", "action" => "profile")
        );

        $batch_result = $this->ecwid_api->get_batch_request($params);

        $categories = $batch_result["c"];
        $products   = $batch_result["p"];
        $profile    = $batch_result["pf"];

        $return = '';

        if (is_array($categories)) 
        {
            foreach ($categories as $category) 
            {
                $category_url = $this->build_url($category["url"]);
                $category_name = $category["name"];
                $return .= "<div class='ecwid_catalog_category_name'><a href='" . htmlspecialchars($category_url) . "&amp;offset=0&amp;sort=nameAsc'>" . $category_name . "</a><br /></div>" . PHP_EOL;
            }
        }

        if (is_array($products)) 
        {
            foreach ($products as $product) 
            {
                $product_url = $this->store_base_url . "#!/~/product/category=" . $id . "&id=" . $product["id"];
                $this->build_url($product["url"]);
                $product_name = $product["name"];
                $product_price = $product["price"] . "&nbsp;" . $profile["currency"];
                $return .= "<div>";
                $return .= "<span class='ecwid_product_name'><a href='" . htmlspecialchars($product_url) . "'>" . $product_name . "</a></span>";
                $return .= "&nbsp;&nbsp;<span class='ecwid_product_price'>" . $product_price . "</span>";
                $return .= "</div>" . PHP_EOL;
            }
        }

        return $return;
    }

	function get_category_name($id)
    {
        $categories = $this->ecwid_api->get_all_categories();
        
        foreach ($categories as $cat) {

            if ($cat['id'] == $id)
                return $cat['name'];

        }
    }

	function get_category_description($id)
    {
        $categories = $this->ecwid_api->get_all_categories();

		foreach ($categories as $cat) {

			if ($cat['id'] == $id) {

				$description = $cat['description'];

				break;

			}
		}
		$description = $product['description'];

        $description = strip_tags($description);
        $description = html_entity_decode($description);
        $description = trim($description, " \t\xA0\n\r");// Space, tab, non-breaking space, newline, carriage return
        $description = mb_substr($description, 0, 160, 'utf-8');

		return $description;

	}

    function build_url($url_from_ecwid)
    {
        if (preg_match('/(.*)(#!)(.*)/', $url_from_ecwid, $matches))
            return $this->store_base_url . $matches[2] . $matches[3]; 
        else
            return '';
    }
}

class EcwidProductApi {
	var $store_id = '';

	var $error = '';

	var $error_code = '';

	var $ECWID_PRODUCT_API_ENDPOINT = "http://app.ecwid.com/api/v1";
	
	function __construct($store_id) {
		$this->store_id = intval($store_id);
	}

	function EcwidProductApi($store_id) {
		if(version_compare(PHP_VERSION,"5.0.0","<")) {
			$this->__construct($store_id);
		}
	}

	function internal_parse_json($json) {
    if(version_compare(PHP_VERSION,"5.2.0",">=")) {
      return json_decode($json, true);
     }
		$json_parser = new Services_JSON(SERVICES_JSON_LOOSE_TYPE);
		return $json_parser->decode($json);
	}

	function internal_fetch_url_libcurl($url) {
		if (intval($timeout) <= 0)
			$timeout = 90;
		if (!function_exists('curl_init'))
			return array("code"=>"0","data"=>"libcurl is not installed");
		$headers[] = "Content-Type: application/x-www-form-urlencoded";
		$ch = curl_init();

		curl_setopt ($ch, CURLOPT_URL, $url);
		curl_setopt ($ch, CURLOPT_HEADER, 0);
		curl_setopt ($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt ($ch, CURLOPT_HTTPGET, 1);
		curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
		
		$body = curl_exec ($ch);
		$errno = curl_errno ($ch);
		$error = curl_error($ch);

		$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$result = array();
		if( $error ) {
			return array("code"=>"0","data"=>"libcurl error($errno): $error");
		}

		return array("code"=>$httpcode, "data"=>$body);
	}

	function process_request($url) {
		$result = $this->internal_fetch_url_libcurl($url);
		if ($result['code'] == 200) {
			$this->error = '';
			$this->error_code = '';
			$json = $result['data'];
			return $this->internal_parse_json($json);
		} else {
			$this->error = $result['data'];
			$this->error_code = $result['code'];
			return false;
		}
	}

	function get_all_categories() {
		$api_url = $this->ECWID_PRODUCT_API_ENDPOINT . "/" . $this->store_id . "/categories";
		$categories = $this->process_request($api_url);
		return $categories;
	}

	function get_subcategories_by_id($parent_category_id = 0) {
		$parent_category_id = intval($parent_category_id);
		$api_url = $this->ECWID_PRODUCT_API_ENDPOINT . "/" . $this->store_id . "/categories?parent=" .
				$parent_category_id;
		$categories = $this->process_request($api_url);
		return $categories;
	}

	function get_all_products() {
		$api_url = $this->ECWID_PRODUCT_API_ENDPOINT . "/" . $this->store_id . "/products";
		$products = $this->process_request($api_url);
		return $products;
	}


	function get_products_by_category_id($category_id = 0) {
		$category_id = intval($category_id);
		$api_url = $this->ECWID_PRODUCT_API_ENDPOINT . "/" . $this->store_id . "/products?category=" . $category_id;
		$products = $this->process_request($api_url);
		return $products;
	}

	function get_product($product_id) {
		$product_id = intval($product_id);
		$api_url = $this->ECWID_PRODUCT_API_ENDPOINT . "/" . $this->store_id . "/product?id=" . $product_id;
		$product = $this->process_request($api_url);
		return $product;
	}

	function get_batch_request($params) {
		if (!is_array($params)) {
			return false;
		} else {
			$api_url = '';
			foreach ($params as $param) {
				$alias = $param["alias"];
				$action = $param["action"];
				$action_params = $param["params"];
				if (!empty($api_url))
					$api_url .= "&";

				$api_url .= ($alias . "=" . $action);

					// if there are the parameters - add it to url
				if (is_array($action_params)) {
					$action_param_str = "?";
					$is_first = true;
					foreach ($action_params as $action_param_name => $action_param_value) {
						if (!$is_first) {
							$action_param_str .= "&";
						}
						$action_param_str .= $action_param_name . "=" . $action_param_value;
						$is_first = false;
					}
					$action_param_str = urlencode($action_param_str);
					$api_url .= $action_param_str;
				}
			}
			
			$api_url =  $this->ECWID_PRODUCT_API_ENDPOINT . "/" . $this->store_id . "/batch?". $api_url;
			$data = $this->process_request($api_url);
			return $data;
		}
	}

	function get_random_products($count) {
	  $count = intval($count);
		$api_url = $this->ECWID_PRODUCT_API_ENDPOINT . "/" . $this->store_id . "/random_products?count=" . $count;
		$random_products = $this->process_request($api_url);
		return $random_products;
	}
	
	function get_profile() {
		$api_url = $this->ECWID_PRODUCT_API_ENDPOINT . "/" . $this->store_id . "/profile";
		$profile = $this->process_request($api_url);
		return $profile;
	}

  function is_api_enabled() {
    // quick and lightweight request
    $api_url = $this->ECWID_PRODUCT_API_ENDPOINT . "/" . $this->store_id . "/profile";
    $this->process_request($api_url);
    if ($this->error_code === '') {
      return true;
    } else {
      return false;
    }
  }
}

// JSON code start
/**
 * Converts to and from JSON format.
 *
 * JSON (JavaScript Object Notation) is a lightweight data-interchange
 * format. It is easy for humans to read and write. It is easy for machines
 * to parse and generate. It is based on a subset of the JavaScript
 * Programming Language, Standard ECMA-262 3rd Edition - December 1999.
 * This feature can also be found in  Python. JSON is a text format that is
 * completely language independent but uses conventions that are familiar
 * to programmers of the C-family of languages, including C, C++, C#, Java,
 * JavaScript, Perl, TCL, and many others. These properties make JSON an
 * ideal data-interchange language.
 *
 * This package provides a simple encoder and decoder for JSON notation. It
 * is intended for use with client-side Javascript applications that make
 * use of HTTPRequest to perform server communication functions - data can
 * be encoded into JSON notation for use in a client-side javascript, or
 * decoded from incoming Javascript requests. JSON format is native to
 * Javascript, and can be directly eval()'ed with no further parsing
 * overhead
 *
 * All strings should be in ASCII or UTF-8 format!
 *
 * LICENSE: Redistribution and use in source and binary forms, with or
 * without modification, are permitted provided that the following
 * conditions are met: Redistributions of source code must retain the
 * above copyright notice, this list of conditions and the following
 * disclaimer. Redistributions in binary form must reproduce the above
 * copyright notice, this list of conditions and the following disclaimer
 * in the documentation and/or other materials provided with the
 * distribution.
 *
 * THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED
 * WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF
 * MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN
 * NO EVENT SHALL CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
 * BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS
 * OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
 * ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR
 * TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE
 * USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH
 * DAMAGE.
 *
 * @category
 * @package     Services_JSON
 * @author      Michal Migurski <mike-json@teczno.com>
 * @author      Matt Knapp <mdknapp[at]gmail[dot]com>
 * @author      Brett Stimmerman <brettstimmerman[at]gmail[dot]com>
 * @copyright   2005 Michal Migurski
 * @version     CVS: $Id: JSON.php,v 1.31 2006/06/28 05:54:17 migurski Exp $
 * @license     http://www.opensource.org/licenses/bsd-license.php
 * @link        http://pear.php.net/pepr/pepr-proposal-show.php?id=198
 */

/**
 * Marker constant for Services_JSON::decode(), used to flag stack state
 */
define('SERVICES_JSON_SLICE',   1);

/**
 * Marker constant for Services_JSON::decode(), used to flag stack state
 */
define('SERVICES_JSON_IN_STR',  2);

/**
 * Marker constant for Services_JSON::decode(), used to flag stack state
 */
define('SERVICES_JSON_IN_ARR',  3);

/**
 * Marker constant for Services_JSON::decode(), used to flag stack state
 */
define('SERVICES_JSON_IN_OBJ',  4);

/**
 * Marker constant for Services_JSON::decode(), used to flag stack state
 */
define('SERVICES_JSON_IN_CMT', 5);

/**
 * Behavior switch for Services_JSON::decode()
 */
define('SERVICES_JSON_LOOSE_TYPE', 16);

/**
 * Behavior switch for Services_JSON::decode()
 */
define('SERVICES_JSON_SUPPRESS_ERRORS', 32);

/**
 * Converts to and from JSON format.
 *
 * Brief example of use:
 *
 * <code>
 * // create a new instance of Services_JSON
 * $json = new Services_JSON();
 *
 * // convert a complexe value to JSON notation, and send it to the browser
 * $value = array('foo', 'bar', array(1, 2, 'baz'), array(3, array(4)));
 * $output = $json->encode($value);
 *
 * print($output);
 * // prints: ["foo","bar",[1,2,"baz"],[3,[4]]]
 *
 * // accept incoming POST data, assumed to be in JSON notation
 * $input = file_get_contents('php://input', 1000000);
 * $value = $json->decode($input);
 * </code>
 */
class Services_JSON
{
   /**
    * constructs a new JSON instance
    *
    * @param    int     $use    object behavior flags; combine with boolean-OR
    *
    *                           possible values:
    *                           - SERVICES_JSON_LOOSE_TYPE:  loose typing.
    *                                   "{...}" syntax creates associative arrays
    *                                   instead of objects in decode().
    *                           - SERVICES_JSON_SUPPRESS_ERRORS:  error suppression.
    *                                   Values which can't be encoded (e.g. resources)
    *                                   appear as NULL instead of throwing errors.
    *                                   By default, a deeply-nested resource will
    *                                   bubble up with an error, so all return values
    *                                   from encode() should be checked with isError()
    */
    function Services_JSON($use = 0)
    {
        $this->use = $use;
    }

   /**
    * convert a string from one UTF-16 char to one UTF-8 char
    *
    * Normally should be handled by mb_convert_encoding, but
    * provides a slower PHP-only method for installations
    * that lack the multibye string extension.
    *
    * @param    string  $utf16  UTF-16 character
    * @return   string  UTF-8 character
    * @access   private
    */
    function utf162utf8($utf16)
    {
        // oh please oh please oh please oh please oh please
        if(function_exists('mb_convert_encoding')) {
            return mb_convert_encoding($utf16, 'UTF-8', 'UTF-16');
        }

        $bytes = (ord($utf16{0}) << 8) | ord($utf16{1});

        switch(true) {
            case ((0x7F & $bytes) == $bytes):
                // this case should never be reached, because we are in ASCII range
                // see: http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
                return chr(0x7F & $bytes);

            case (0x07FF & $bytes) == $bytes:
                // return a 2-byte UTF-8 character
                // see: http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
                return chr(0xC0 | (($bytes >> 6) & 0x1F))
                     . chr(0x80 | ($bytes & 0x3F));

            case (0xFFFF & $bytes) == $bytes:
                // return a 3-byte UTF-8 character
                // see: http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
                return chr(0xE0 | (($bytes >> 12) & 0x0F))
                     . chr(0x80 | (($bytes >> 6) & 0x3F))
                     . chr(0x80 | ($bytes & 0x3F));
        }

        // ignoring UTF-32 for now, sorry
        return '';
    }

   /**
    * convert a string from one UTF-8 char to one UTF-16 char
    *
    * Normally should be handled by mb_convert_encoding, but
    * provides a slower PHP-only method for installations
    * that lack the multibye string extension.
    *
    * @param    string  $utf8   UTF-8 character
    * @return   string  UTF-16 character
    * @access   private
    */
    function utf82utf16($utf8)
    {
        // oh please oh please oh please oh please oh please
        if(function_exists('mb_convert_encoding')) {
            return mb_convert_encoding($utf8, 'UTF-16', 'UTF-8');
        }

        switch(strlen($utf8)) {
            case 1:
                // this case should never be reached, because we are in ASCII range
                // see: http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
                return $utf8;

            case 2:
                // return a UTF-16 character from a 2-byte UTF-8 char
                // see: http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
                return chr(0x07 & (ord($utf8{0}) >> 2))
                     . chr((0xC0 & (ord($utf8{0}) << 6))
                         | (0x3F & ord($utf8{1})));

            case 3:
                // return a UTF-16 character from a 3-byte UTF-8 char
                // see: http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
                return chr((0xF0 & (ord($utf8{0}) << 4))
                         | (0x0F & (ord($utf8{1}) >> 2)))
                     . chr((0xC0 & (ord($utf8{1}) << 6))
                         | (0x7F & ord($utf8{2})));
        }

        // ignoring UTF-32 for now, sorry
        return '';
    }

   /**
    * encodes an arbitrary variable into JSON format
    *
    * @param    mixed   $var    any number, boolean, string, array, or object to be encoded.
    *                           see argument 1 to Services_JSON() above for array-parsing behavior.
    *                           if var is a strng, note that encode() always expects it
    *                           to be in ASCII or UTF-8 format!
    *
    * @return   mixed   JSON string representation of input var or an error if a problem occurs
    * @access   public
    */
    function encode($var)
    {
        switch (gettype($var)) {
            case 'boolean':
                return $var ? 'true' : 'false';

            case 'NULL':
                return 'null';

            case 'integer':
                return (int) $var;

            case 'double':
            case 'float':
                return (float) $var;

            case 'string':
                // STRINGS ARE EXPECTED TO BE IN ASCII OR UTF-8 FORMAT
                $ascii = '';
                $strlen_var = strlen($var);

               /*
                * Iterate over every character in the string,
                * escaping with a slash or encoding to UTF-8 where necessary
                */
                for ($c = 0; $c < $strlen_var; ++$c) {

                    $ord_var_c = ord($var{$c});

                    switch (true) {
                        case $ord_var_c == 0x08:
                            $ascii .= '\b';
                            break;
                        case $ord_var_c == 0x09:
                            $ascii .= '\t';
                            break;
                        case $ord_var_c == 0x0A:
                            $ascii .= '\n';
                            break;
                        case $ord_var_c == 0x0C:
                            $ascii .= '\f';
                            break;
                        case $ord_var_c == 0x0D:
                            $ascii .= '\r';
                            break;

                        case $ord_var_c == 0x22:
                        case $ord_var_c == 0x2F:
                        case $ord_var_c == 0x5C:
                            // double quote, slash, slosh
                            $ascii .= '\\'.$var{$c};
                            break;

                        case (($ord_var_c >= 0x20) && ($ord_var_c <= 0x7F)):
                            // characters U-00000000 - U-0000007F (same as ASCII)
                            $ascii .= $var{$c};
                            break;

                        case (($ord_var_c & 0xE0) == 0xC0):
                            // characters U-00000080 - U-000007FF, mask 110XXXXX
                            // see http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
                            $char = pack('C*', $ord_var_c, ord($var{$c + 1}));
                            $c += 1;
                            $utf16 = $this->utf82utf16($char);
                            $ascii .= sprintf('\u%04s', bin2hex($utf16));
                            break;

                        case (($ord_var_c & 0xF0) == 0xE0):
                            // characters U-00000800 - U-0000FFFF, mask 1110XXXX
                            // see http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
                            $char = pack('C*', $ord_var_c,
                                         ord($var{$c + 1}),
                                         ord($var{$c + 2}));
                            $c += 2;
                            $utf16 = $this->utf82utf16($char);
                            $ascii .= sprintf('\u%04s', bin2hex($utf16));
                            break;

                        case (($ord_var_c & 0xF8) == 0xF0):
                            // characters U-00010000 - U-001FFFFF, mask 11110XXX
                            // see http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
                            $char = pack('C*', $ord_var_c,
                                         ord($var{$c + 1}),
                                         ord($var{$c + 2}),
                                         ord($var{$c + 3}));
                            $c += 3;
                            $utf16 = $this->utf82utf16($char);
                            $ascii .= sprintf('\u%04s', bin2hex($utf16));
                            break;

                        case (($ord_var_c & 0xFC) == 0xF8):
                            // characters U-00200000 - U-03FFFFFF, mask 111110XX
                            // see http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
                            $char = pack('C*', $ord_var_c,
                                         ord($var{$c + 1}),
                                         ord($var{$c + 2}),
                                         ord($var{$c + 3}),
                                         ord($var{$c + 4}));
                            $c += 4;
                            $utf16 = $this->utf82utf16($char);
                            $ascii .= sprintf('\u%04s', bin2hex($utf16));
                            break;

                        case (($ord_var_c & 0xFE) == 0xFC):
                            // characters U-04000000 - U-7FFFFFFF, mask 1111110X
                            // see http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
                            $char = pack('C*', $ord_var_c,
                                         ord($var{$c + 1}),
                                         ord($var{$c + 2}),
                                         ord($var{$c + 3}),
                                         ord($var{$c + 4}),
                                         ord($var{$c + 5}));
                            $c += 5;
                            $utf16 = $this->utf82utf16($char);
                            $ascii .= sprintf('\u%04s', bin2hex($utf16));
                            break;
                    }
                }

                return '"'.$ascii.'"';

            case 'array':
               /*
                * As per JSON spec if any array key is not an integer
                * we must treat the the whole array as an object. We
                * also try to catch a sparsely populated associative
                * array with numeric keys here because some JS engines
                * will create an array with empty indexes up to
                * max_index which can cause memory issues and because
                * the keys, which may be relevant, will be remapped
                * otherwise.
                *
                * As per the ECMA and JSON specification an object may
                * have any string as a property. Unfortunately due to
                * a hole in the ECMA specification if the key is a
                * ECMA reserved word or starts with a digit the
                * parameter is only accessible using ECMAScript's
                * bracket notation.
                */

                // treat as a JSON object
                if (is_array($var) && count($var) && (array_keys($var) !== range(0, sizeof($var) - 1))) {
                    $properties = array_map(array($this, 'name_value'),
                                            array_keys($var),
                                            array_values($var));

                    foreach($properties as $property) {
                        if(Services_JSON::isError($property)) {
                            return $property;
                        }
                    }

                    return '{' . join(',', $properties) . '}';
                }

                // treat it like a regular array
                $elements = array_map(array($this, 'encode'), $var);

                foreach($elements as $element) {
                    if(Services_JSON::isError($element)) {
                        return $element;
                    }
                }

                return '[' . join(',', $elements) . ']';

            case 'object':
                $vars = get_object_vars($var);

                $properties = array_map(array($this, 'name_value'),
                                        array_keys($vars),
                                        array_values($vars));

                foreach($properties as $property) {
                    if(Services_JSON::isError($property)) {
                        return $property;
                    }
                }

                return '{' . join(',', $properties) . '}';

            default:
                return ($this->use & SERVICES_JSON_SUPPRESS_ERRORS)
                    ? 'null'
                    : new Services_JSON_Error(gettype($var)." can not be encoded as JSON string");
        }
    }

   /**
    * array-walking function for use in generating JSON-formatted name-value pairs
    *
    * @param    string  $name   name of key to use
    * @param    mixed   $value  reference to an array element to be encoded
    *
    * @return   string  JSON-formatted name-value pair, like '"name":value'
    * @access   private
    */
    function name_value($name, $value)
    {
        $encoded_value = $this->encode($value);

        if(Services_JSON::isError($encoded_value)) {
            return $encoded_value;
        }

        return $this->encode(strval($name)) . ':' . $encoded_value;
    }

   /**
    * reduce a string by removing leading and trailing comments and whitespace
    *
    * @param    $str    string      string value to strip of comments and whitespace
    *
    * @return   string  string value stripped of comments and whitespace
    * @access   private
    */
    function reduce_string($str)
    {
        $str = preg_replace(array(

                // eliminate single line comments in '// ...' form
                '#^\s*//(.+)$#m',

                // eliminate multi-line comments in '/* ... */' form, at start of string
                '#^\s*/\*(.+)\*/#Us',

                // eliminate multi-line comments in '/* ... */' form, at end of string
                '#/\*(.+)\*/\s*$#Us'

            ), '', $str);

        // eliminate extraneous space
        return trim($str);
    }

   /**
    * decodes a JSON string into appropriate variable
    *
    * @param    string  $str    JSON-formatted string
    *
    * @return   mixed   number, boolean, string, array, or object
    *                   corresponding to given JSON input string.
    *                   See argument 1 to Services_JSON() above for object-output behavior.
    *                   Note that decode() always returns strings
    *                   in ASCII or UTF-8 format!
    * @access   public
    */
    function decode($str)
    {
        $str = $this->reduce_string($str);

        switch (strtolower($str)) {
            case 'true':
                return true;

            case 'false':
                return false;

            case 'null':
                return null;

            default:
                $m = array();

                if (is_numeric($str)) {
                    // Lookie-loo, it's a number

                    // This would work on its own, but I'm trying to be
                    // good about returning integers where appropriate:
                    // return (float)$str;

                    // Return float or int, as appropriate
                    return ((float)$str == (integer)$str)
                        ? (integer)$str
                        : (float)$str;

                } elseif (preg_match('/^("|\').*(\1)$/s', $str, $m) && $m[1] == $m[2]) {
                    // STRINGS RETURNED IN UTF-8 FORMAT
                    $delim = substr($str, 0, 1);
                    $chrs = substr($str, 1, -1);
                    $utf8 = '';
                    $strlen_chrs = strlen($chrs);

                    for ($c = 0; $c < $strlen_chrs; ++$c) {

                        $substr_chrs_c_2 = substr($chrs, $c, 2);
                        $ord_chrs_c = ord($chrs{$c});

                        switch (true) {
                            case $substr_chrs_c_2 == '\b':
                                $utf8 .= chr(0x08);
                                ++$c;
                                break;
                            case $substr_chrs_c_2 == '\t':
                                $utf8 .= chr(0x09);
                                ++$c;
                                break;
                            case $substr_chrs_c_2 == '\n':
                                $utf8 .= chr(0x0A);
                                ++$c;
                                break;
                            case $substr_chrs_c_2 == '\f':
                                $utf8 .= chr(0x0C);
                                ++$c;
                                break;
                            case $substr_chrs_c_2 == '\r':
                                $utf8 .= chr(0x0D);
                                ++$c;
                                break;

                            case $substr_chrs_c_2 == '\\"':
                            case $substr_chrs_c_2 == '\\\'':
                            case $substr_chrs_c_2 == '\\\\':
                            case $substr_chrs_c_2 == '\\/':
                                if (($delim == '"' && $substr_chrs_c_2 != '\\\'') ||
                                   ($delim == "'" && $substr_chrs_c_2 != '\\"')) {
                                    $utf8 .= $chrs{++$c};
                                }
                                break;

                            case preg_match('/\\\u[0-9A-F]{4}/i', substr($chrs, $c, 6)):
                                // single, escaped unicode character
                                $utf16 = chr(hexdec(substr($chrs, ($c + 2), 2)))
                                       . chr(hexdec(substr($chrs, ($c + 4), 2)));
                                $utf8 .= $this->utf162utf8($utf16);
                                $c += 5;
                                break;

                            case ($ord_chrs_c >= 0x20) && ($ord_chrs_c <= 0x7F):
                                $utf8 .= $chrs{$c};
                                break;

                            case ($ord_chrs_c & 0xE0) == 0xC0:
                                // characters U-00000080 - U-000007FF, mask 110XXXXX
                                //see http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
                                $utf8 .= substr($chrs, $c, 2);
                                ++$c;
                                break;

                            case ($ord_chrs_c & 0xF0) == 0xE0:
                                // characters U-00000800 - U-0000FFFF, mask 1110XXXX
                                // see http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
                                $utf8 .= substr($chrs, $c, 3);
                                $c += 2;
                                break;

                            case ($ord_chrs_c & 0xF8) == 0xF0:
                                // characters U-00010000 - U-001FFFFF, mask 11110XXX
                                // see http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
                                $utf8 .= substr($chrs, $c, 4);
                                $c += 3;
                                break;

                            case ($ord_chrs_c & 0xFC) == 0xF8:
                                // characters U-00200000 - U-03FFFFFF, mask 111110XX
                                // see http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
                                $utf8 .= substr($chrs, $c, 5);
                                $c += 4;
                                break;

                            case ($ord_chrs_c & 0xFE) == 0xFC:
                                // characters U-04000000 - U-7FFFFFFF, mask 1111110X
                                // see http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
                                $utf8 .= substr($chrs, $c, 6);
                                $c += 5;
                                break;

                        }

                    }

                    return $utf8;

                } elseif (preg_match('/^\[.*\]$/s', $str) || preg_match('/^\{.*\}$/s', $str)) {
                    // array, or object notation

                    if ($str{0} == '[') {
                        $stk = array(SERVICES_JSON_IN_ARR);
                        $arr = array();
                    } else {
                        if ($this->use & SERVICES_JSON_LOOSE_TYPE) {
                            $stk = array(SERVICES_JSON_IN_OBJ);
                            $obj = array();
                        } else {
                            $stk = array(SERVICES_JSON_IN_OBJ);
                            $obj = new stdClass();
                        }
                    }

                    array_push($stk, array('what'  => SERVICES_JSON_SLICE,
                                           'where' => 0,
                                           'delim' => false));

                    $chrs = substr($str, 1, -1);
                    $chrs = $this->reduce_string($chrs);

                    if ($chrs == '') {
                        if (reset($stk) == SERVICES_JSON_IN_ARR) {
                            return $arr;

                        } else {
                            return $obj;

                        }
                    }

                    //print("\nparsing {$chrs}\n");

                    $strlen_chrs = strlen($chrs);

                    for ($c = 0; $c <= $strlen_chrs; ++$c) {

                        $top = end($stk);
                        $substr_chrs_c_2 = substr($chrs, $c, 2);

                        if (($c == $strlen_chrs) || (($chrs{$c} == ',') && ($top['what'] == SERVICES_JSON_SLICE))) {
                            // found a comma that is not inside a string, array, etc.,
                            // OR we've reached the end of the character list
                            $slice = substr($chrs, $top['where'], ($c - $top['where']));
                            array_push($stk, array('what' => SERVICES_JSON_SLICE, 'where' => ($c + 1), 'delim' => false));
                            //print("Found split at {$c}: ".substr($chrs, $top['where'], (1 + $c - $top['where']))."\n");

                            if (reset($stk) == SERVICES_JSON_IN_ARR) {
                                // we are in an array, so just push an element onto the stack
                                array_push($arr, $this->decode($slice));

                            } elseif (reset($stk) == SERVICES_JSON_IN_OBJ) {
                                // we are in an object, so figure
                                // out the property name and set an
                                // element in an associative array,
                                // for now
                                $parts = array();
                                
                                if (preg_match('/^\s*(["\'].*[^\\\]["\'])\s*:\s*(\S.*),?$/Uis', $slice, $parts)) {
                                    // "name":value pair
                                    $key = $this->decode($parts[1]);
                                    $val = $this->decode($parts[2]);

                                    if ($this->use & SERVICES_JSON_LOOSE_TYPE) {
                                        $obj[$key] = $val;
                                    } else {
                                        $obj->$key = $val;
                                    }
                                } elseif (preg_match('/^\s*(\w+)\s*:\s*(\S.*),?$/Uis', $slice, $parts)) {
                                    // name:value pair, where name is unquoted
                                    $key = $parts[1];
                                    $val = $this->decode($parts[2]);

                                    if ($this->use & SERVICES_JSON_LOOSE_TYPE) {
                                        $obj[$key] = $val;
                                    } else {
                                        $obj->$key = $val;
                                    }
                                }

                            }

                        } elseif ((($chrs{$c} == '"') || ($chrs{$c} == "'")) && ($top['what'] != SERVICES_JSON_IN_STR)) {
                            // found a quote, and we are not inside a string
                            array_push($stk, array('what' => SERVICES_JSON_IN_STR, 'where' => $c, 'delim' => $chrs{$c}));
                            //print("Found start of string at {$c}\n");

                        } elseif (($chrs{$c} == $top['delim']) &&
                                 ($top['what'] == SERVICES_JSON_IN_STR) &&
                                 ((strlen(substr($chrs, 0, $c)) - strlen(rtrim(substr($chrs, 0, $c), '\\'))) % 2 != 1)) {
                            // found a quote, we're in a string, and it's not escaped
                            // we know that it's not escaped becase there is _not_ an
                            // odd number of backslashes at the end of the string so far
                            array_pop($stk);
                            //print("Found end of string at {$c}: ".substr($chrs, $top['where'], (1 + 1 + $c - $top['where']))."\n");

                        } elseif (($chrs{$c} == '[') &&
                                 in_array($top['what'], array(SERVICES_JSON_SLICE, SERVICES_JSON_IN_ARR, SERVICES_JSON_IN_OBJ))) {
                            // found a left-bracket, and we are in an array, object, or slice
                            array_push($stk, array('what' => SERVICES_JSON_IN_ARR, 'where' => $c, 'delim' => false));
                            //print("Found start of array at {$c}\n");

                        } elseif (($chrs{$c} == ']') && ($top['what'] == SERVICES_JSON_IN_ARR)) {
                            // found a right-bracket, and we're in an array
                            array_pop($stk);
                            //print("Found end of array at {$c}: ".substr($chrs, $top['where'], (1 + $c - $top['where']))."\n");

                        } elseif (($chrs{$c} == '{') &&
                                 in_array($top['what'], array(SERVICES_JSON_SLICE, SERVICES_JSON_IN_ARR, SERVICES_JSON_IN_OBJ))) {
                            // found a left-brace, and we are in an array, object, or slice
                            array_push($stk, array('what' => SERVICES_JSON_IN_OBJ, 'where' => $c, 'delim' => false));
                            //print("Found start of object at {$c}\n");

                        } elseif (($chrs{$c} == '}') && ($top['what'] == SERVICES_JSON_IN_OBJ)) {
                            // found a right-brace, and we're in an object
                            array_pop($stk);
                            //print("Found end of object at {$c}: ".substr($chrs, $top['where'], (1 + $c - $top['where']))."\n");

                        } elseif (($substr_chrs_c_2 == '/*') &&
                                 in_array($top['what'], array(SERVICES_JSON_SLICE, SERVICES_JSON_IN_ARR, SERVICES_JSON_IN_OBJ))) {
                            // found a comment start, and we are in an array, object, or slice
                            array_push($stk, array('what' => SERVICES_JSON_IN_CMT, 'where' => $c, 'delim' => false));
                            $c++;
                            //print("Found start of comment at {$c}\n");

                        } elseif (($substr_chrs_c_2 == '*/') && ($top['what'] == SERVICES_JSON_IN_CMT)) {
                            // found a comment end, and we're in one now
                            array_pop($stk);
                            $c++;

                            for ($i = $top['where']; $i <= $c; ++$i)
                                $chrs = substr_replace($chrs, ' ', $i, 1);

                            //print("Found end of comment at {$c}: ".substr($chrs, $top['where'], (1 + $c - $top['where']))."\n");

                        }

                    }

                    if (reset($stk) == SERVICES_JSON_IN_ARR) {
                        return $arr;

                    } elseif (reset($stk) == SERVICES_JSON_IN_OBJ) {
                        return $obj;

                    }

                }
        }
    }

    /**
     * @todo Ultimately, this should just call PEAR::isError()
     */
    function isError($data, $code = null)
    {
        if (class_exists('pear')) {
            return PEAR::isError($data, $code);
        } elseif (is_object($data) && (get_class($data) == 'services_json_error' ||
                                 is_subclass_of($data, 'services_json_error'))) {
            return true;
        }

        return false;
    }
}

if (class_exists('PEAR_Error')) {

    class Services_JSON_Error extends PEAR_Error
    {
        function Services_JSON_Error($message = 'unknown error', $code = null,
                                     $mode = null, $options = null, $userinfo = null)
        {
            parent::PEAR_Error($message, $code, $mode, $options, $userinfo);
        }
    }

} else {

    /**
     * @todo Ultimately, this class shall be descended from PEAR_Error
     */
    class Services_JSON_Error
    {
        function Services_JSON_Error($message = 'unknown error', $code = null,
                                     $mode = null, $options = null, $userinfo = null)
        {

        }
    }

}
// JSON code end

function show_ecwid($params) {
	$store_id = $params['store_id'];
	if (empty($store_id)) {
	  $store_id = '1003'; //demo mode
	}
		
	$list_of_views = $params['list_of_views'];

    if (is_array($list_of_views))    
    	foreach ($list_of_views as $k=>$v) {
    		if (!in_array($v, array('list','grid','table'))) unset($list_of_views[$k]);
	}
	
	if ((!is_array($list_of_views)) || empty($list_of_views)) {
		$list_of_views = array('list','grid','table');
	}

	$ecwid_pb_categoriesperrow = $params['ecwid_pb_categoriesperrow'];
	if (empty($ecwid_pb_categoriesperrow)) {
		$ecwid_pb_categoriesperrow = 3;
	}
	$ecwid_pb_productspercolumn_grid = $params['ecwid_pb_productspercolumn_grid'];
	if (empty($ecwid_pb_productspercolumn_grid)) {
		$ecwid_pb_productspercolumn_grid = 3;
	}
	$ecwid_pb_productsperrow_grid = $params['ecwid_pb_productsperrow_grid'];
	if (empty($ecwid_pb_productsperrow_grid)) {
		$ecwid_pb_productsperrow_grid = 3;
	}
	$ecwid_pb_productsperpage_list = $params['ecwid_pb_productsperpage_list'];
	if (empty($ecwid_pb_productsperpage_list)) {
		$ecwid_pb_productsperpage_list = 10;
	}
	$ecwid_pb_productsperpage_table = $params['ecwid_pb_productsperpage_table'];
	if (empty($ecwid_pb_productsperpage_table)) {
		$ecwid_pb_productsperpage_table = 20;
	}
	$ecwid_pb_defaultview = $params['ecwid_pb_defaultview'];
	if (empty($ecwid_pb_defaultview) || !in_array($ecwid_pb_defaultview, $list_of_views)) {
		$ecwid_pb_defaultview = 'grid';
	}
	$ecwid_pb_searchview = $params['ecwid_pb_searchview'];
	if (empty($ecwid_pb_searchview) || !in_array($ecwid_pb_searchview, $list_of_views)) {
		$ecwid_pb_searchview = 'list';
	}
	$ecwid_enable_html_mode = $params['ecwid_enable_html_mode'];
	if (empty($ecwid_enable_html_mode)) {
		$ecwid_enable_html_mode = false;
	}

	$ecwid_com = "app.ecwid.com";


	$ecwid_default_category_id = $params['ecwid_default_category_id'];
	
	$ecwid_show_seo_catalog = $params['ecwid_show_seo_catalog'];
	if (empty($ecwid_show_seo_catalog)) {
		$ecwid_show_seo_catalog = false;
	}

 	$ecwid_mobile_catalog_link = $params['ecwid_mobile_catalog_link'];
	if (empty($ecwid_mobile_catalog_link)) {
		$ecwid_mobile_catalog_link = "//$ecwid_com/jsp/$store_id/catalog";
	}

  $html_catalog = '';
	if ($ecwid_show_seo_catalog) {
    if (!empty($_GET['ecwid_product_id'])) {
      $ecwid_open_product = '<script type="text/javascript"> if (!document.location.hash) document.location.hash = "ecwid:category=0&mode=product&product='. intval($_GET['ecwid_product_id']) .'";</script>';
     } elseif (!empty($_GET['ecwid_category_id'])) {
       $ecwid_default_category_id = intval($_GET['ecwid_category_id']);
     }
		$html_catalog = show_ecwid_catalog($store_id);
	}
	
	if (empty($html_catalog)) {
		$html_catalog = "Your browser does not support JavaScript.<a href=\"{$ecwid_mobile_catalog_link}\">HTML version of this store</a>";
	}


	if (empty($ecwid_default_category_id)) {
		$ecwid_default_category_str = '';
	} else {
		$ecwid_default_category_str = ',"defaultCategoryId='. $ecwid_default_category_id .'"';
	}

	$ecwid_is_secure_page = $params['ecwid_is_secure_page'];
	if (empty ($ecwid_is_secure_page)) {
		$ecwid_is_secure_page = false;
	}

	$protocol = "http";
	if ($ecwid_is_secure_page) {
		$protocol = "https";
	}

	$ecwid_element_id = "ecwid-inline-catalog";
        if (!empty($params['ecwid_element_id'])) {
            $ecwid_element_id = $params['ecwid_element_id'];
        }
	$integration_code = <<<EOT
<div>
<script type="text/javascript" src="//$ecwid_com/script.js?$store_id"></script>
<div id="$ecwid_element_id">$html_catalog</div>
<script type="text/javascript"> xProductBrowser(
	"categoriesPerRow=$ecwid_pb_categoriesperrow",
	"views=grid($ecwid_pb_productspercolumn_grid,$ecwid_pb_productsperrow_grid) list($ecwid_pb_productsperpage_list) table($ecwid_pb_productsperpage_table)",
	"categoryView=$ecwid_pb_defaultview",
	"searchView=$ecwid_pb_searchview",
	"id=$ecwid_element_id",
	"style="$ecwid_default_category_str);</script>
$ecwid_open_product
</div>
EOT;
  
	return $integration_code;
}

function show_ecwid_catalog($ecwid_store_id) {

	$ecwid_store_id = intval($ecwid_store_id);
	$api = new EcwidProductApi($ecwid_store_id);

	$ecwid_category_id = intval($_GET['ecwid_category_id']);
	$ecwid_product_id = intval($_GET['ecwid_product_id']);

	if (!empty($ecwid_product_id)) {
		$params = array(
			array("alias" => "p", "action" => "product", "params" => array("id" => $ecwid_product_id)),
			array("alias" => "pf", "action" => "profile")
		);
		$batch_result = $api->get_batch_request($params);
		$product = $batch_result["p"];
		$profile = $batch_result["pf"];
	}
	else {
		if (empty($ecwid_category_id)) {
			$ecwid_category_id = 0;
		}
		$params = array(
			array("alias" => "c", "action" => "categories", "params" => array("parent" => $ecwid_category_id)),
			array("alias" => "p", "action" => "products", "params" => array("category" => $ecwid_category_id)),
			array("alias" => "pf", "action" => "profile")
		);

		$batch_result = $api->get_batch_request($params);

		$categories = $batch_result["c"];
		$products = $batch_result["p"];
		$profile = $batch_result["pf"];
	}
	$html = '';

	if (is_array($product)) {
		$html = "<div class='hproduct'>";
		$html .= "<div class='ecwid_catalog_product_image photo'><img src='" . $product["thumbnailUrl"] . "' alt='" . htmlentities($product["sku"],ENT_COMPAT,'UTF-8') . " " . htmlentities($product["name"],ENT_COMPAT,'UTF-8') . "'/></div>";
		$html .= "<div class='ecwid_catalog_product_name fn'>" . htmlentities($product["name"],ENT_COMPAT,'UTF-8') . "</div>";
		$html .= "<div class='ecwid_catalog_product_price price'>Price: " . $product["price"] . "&nbsp;" . $profile["currency"] . "</div>";
		$html .= "<div class='ecwid_catalog_product_description description'>" . $product["description"] . "</div>";
		$html .= "</div>";
	} else {
		if (is_array($categories)) {
			foreach ($categories as $category) {
				$category_url = ecwid_internal_construct_url($category["url"], array("ecwid_category_id" => $category["id"]));
				$category_name = $category["name"];
				$html .= "<div class='ecwid_catalog_category_name'><a href='" . htmlspecialchars($category_url) . "'>" . $category_name . "</a><br /></div>";
			}
		}

		if (is_array($products)) {
			foreach ($products as $product) {
				$product_url = ecwid_internal_construct_url($product["url"], array("ecwid_product_id" => $product["id"]));
				$product_name = $product["name"];
				$product_price = $product["price"] . "&nbsp;" . $profile["currency"];
				$html .= "<div>";
				$html .= "<span class='ecwid_product_name'><a href='" . htmlspecialchars($product_url) . "'>" . $product_name . "</a></span>";
				$html .= "&nbsp;&nbsp;<span class='ecwid_product_price'>" . $product_price . "</span>";
				$html .= "</div>";
			}
		}

	}
	return $html;
}

function ecwid_is_api_enabled($ecwid_store_id) {

	$ecwid_store_id = intval($ecwid_store_id);
	$api = new EcwidProductApi($ecwid_store_id);

	return $api->is_api_enabled();
}

function ecwid_zerolen() {
  foreach (func_get_args() as $arg) {
    if (strlen($arg) == 0) return true;
  }
  return false;
}

function ecwid_get_request_uri() {
static $request_uri = null;

if (is_null($request_uri)) {
    if (isset($_SERVER['REQUEST_URI'])) {
        $request_uri = $_SERVER['REQUEST_URI'];
        return $request_uri;
    }
    if (isset($_SERVER['HTTP_X_ORIGINAL_URL'])) {
        $request_uri = $_SERVER['HTTP_X_ORIGINAL_URL'];
        return $request_uri;
    } else if (isset($_SERVER['HTTP_X_REWRITE_URL'])) {
        $request_uri = $_SERVER['HTTP_X_REWRITE_URL'];
        return $request_uri;
    }

    if (isset($_SERVER['PATH_INFO']) && !ecwid_zerolen($_SERVER['PATH_INFO'])) {
        if ($_SERVER['PATH_INFO'] == $_SERVER['PHP_SELF']) {
            $request_uri = $_SERVER['PHP_SELF'];
        } else {
            $request_uri = $_SERVER['PHP_SELF'] . $_SERVER['PATH_INFO'];
        }
    } else {
        $request_uri = $_SERVER['PHP_SELF'];
    }
    # Append query string
    if (isset($_SERVER['argv']) && isset($_SERVER['argv'][0]) && !ecwid_zerolen($_SERVER['argv'][0])) {
        $request_uri .= '?' . $_SERVER['argv'][0];
    } else if (isset($_SERVER['QUERY_STRING']) && !ecwid_zerolen($_SERVER['QUERY_STRING'])) {
        $request_uri .= '?' . $_SERVER['QUERY_STRING'];
    }    
    }     
    return $request_uri;
}

function ecwid_internal_construct_url($url_with_anchor, $additional_get_params) {
  $request_uri  = parse_url(ecwid_get_request_uri());
  $base_url = $request_uri['path'];

	// extract anchor
	$url_fragments = parse_url($url_with_anchor);
	$anchor = $url_fragments["fragment"];
	// get params
	$get_params = $_GET;
	unset ($get_params["ecwid_category_id"]);
	unset ($get_params["ecwid_product_id"]);
	$get_params = array_merge($get_params, $additional_get_params);

		// add GET parameters
	if (count($get_params) > 0) {
		$base_url .= "?";
		$is_first = true;
		foreach ($get_params as $key => $value) {
			if (!$is_first) {
				$base_url .= "&";
			}
			$base_url .= $key . "=" . $value;
			$is_first = false;
		}
	}

	// add url anchor (if needed)
	if ($anchor != "") {
		$base_url .= "#" . $anchor;
	}

	return $base_url;
}

function ecwid_parse_escaped_fragment($escaped_fragment) {
    $fragment = urldecode($escaped_fragment);
    $return = array();

    if (preg_match('/^(\/~\/)([a-z]+)\/(.*)$/', $fragment, $matches)) {
        parse_str($matches[3], $return);
        $return['mode'] = $matches[2];
    }
    return $return;
}

function ecwid_page_url () {

	$port = ($_SERVER['SERVER_PORT'] ==  80 ?  "http://" : "https://");

	$parts = parse_url($_SERVER['REQUEST_URI']);

	$queryParams = array();
	parse_str($parts['query'], $queryParams);
	unset($queryParams['_escaped_fragment_']);

	$queryString = http_build_query($queryParams);
	$url = $parts['path'] . '?' . $queryString;

	return $port . $_SERVER['HTTP_HOST'] . $url;

}

$ecwid_html_index = $ecwid_title = '';

if (isset($_GET['_escaped_fragment_'])) {

    $params = ecwid_parse_escaped_fragment($_GET['_escaped_fragment_']);

    $catalog = new EcwidCatalog($ecwid_store_id, ecwid_page_url());

    if (isset($params['mode']) && !empty($params['mode'])) {

        if ($params['mode'] == 'product') {

	        $ecwid_html_index = $catalog->get_product($params['id']);
	        $ecwid_html_index .= '<script type="text/javascript"> if (!document.location.hash) document.location.hash = "!/~/product/id='. intval($params['id']) .'";</script>';

			$ecwid_title = $catalog->get_product_name($params['id']);

			$ecwid_description = $catalog->get_product_description($params['id']);

        } elseif ($params['mode'] == 'category') {

	        $ecwid_html_index = $catalog->get_category($params['id']);
            $ecwid_default_category_str = ',"defaultCategoryId=' . $params['id'] . '"';

			$ecwid_title = $catalog->get_category_name($params['id']);

			$ecwid_description = $catalog->get_category_description($params['id']);

        }

    } else {

        $ecwid_html_index = $catalog->get_category(0);

    }

}

?>
