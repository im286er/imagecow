<?php
/**
 * Imagecow PHP library (version 0.4)
 *
 * 2012. Created by Oscar Otero (http://oscarotero.com / http://anavallasuiza.com)
 * Original code from phpCan Image class (http://idc.anavallasuiza.com/)
 *
 * Imagecow is released under the GNU Affero GPL version 3.
 * More information at http://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Imagecow;

define('IMAGECOW_ERROR_LOADING', 1);
define('IMAGECOW_ERROR_FUNCTION', 2);
define('IMAGECOW_ERROR_INPUT', 3);

use Imagecow\ImageException;

abstract class Image {
	static $operations = array('resize', 'resizeCrop', 'crop', 'convert');

	protected $image;
	protected $Error;

	static function create ($library = null) {
		if (!$library) {
			$library = extension_loaded('imagick') ? 'Imagick' : 'Gd';
		}

		$class = 'Imagecow\\Libs\\'.$library;

		if (class_exists($class)) {
			return new $class;
		}

		throw new Exception('The image library is not valid');
	}



	/**
	 * static function getResponsiveOperations (string $client_properties, [string $operations])
	 *
	 * Transform the image according the client properties
	 * Returns string
	 */
	static function getResponsiveOperations ($client_properties, $operations = '') {
		if (!$operations) {
			return '';
		}

		$client = array();

		foreach (explode('|', str_replace(' ', '', $client_properties)) as $client_properties) {
			$client_properties = explode(',', $client_properties);
			$client[array_shift($client_properties)] = $client_properties;
		}

		$width = isset($client['dimensions'][0]) ? intval($client['dimensions'][0]) : null;
		$height = isset($client['dimensions'][1]) ? intval($client['dimensions'][1]) : null;

		$transform = array();

		foreach (explode(';', str_replace(' ', '', $operations)) as $operation) {
			if (empty($operation)) {
				continue;
			}

			if (strpos($operation, ':') === false) {
				$transform[] = $operation;
				continue;
			}

			if (!isset($width) || !isset($height)) {
				continue;
			}

			list($rules, $operation) = explode(':', $operation, 2);

			foreach (explode(',', $rules) as $rule) {
				$rule = explode('=', $rule, 2);
				$value = intval($rule[1]);

				switch ($rule[0]) {
					case 'max-width':
						if ($width > $value) {
							continue 2;
						}
						break;

					case 'min-width':
						if ($width < $value) {
							continue 2;
						}
						break;

					case 'width':
						if ($width != $value) {
							continue 2;
						}
						break;

					case 'max-height':
						if ($height > $value) {
							continue 2;
						}
						break;

					case 'min-height':
						if ($height < $value) {
							continue 2;
						}
						break;

					case 'height':
						if ($height != $value) {
							continue 2;
						}
						break;
				}

				$transform[] = $operation;
			}
		}

		return implode('|', $transform);
	}


	/**
	 * public function getImage ()
	 *
	 * Gets the image original object or resource
	 * Returns object/resource/null
	 */
	public function getImage () {
		return $this->image;
	}



	/**
	 * public function getError ()
	 *
	 * Returns an ImageException object or null
	 */
	public function getError () {
		return $this->Error;
	}



	/**
	 * public function setError ()
	 *
	 * Sets an error
	 */
	public function setError ($message = '', $code = null) {
		$this->Error = new ImageException($message, $code);
	}


	/**
	 * public function transform ([string $operations])
	 *
	 * Executes a list of operations
	 * Returns this
	 */
	public function transform ($operations = '') {
		if (!$operations) {
			return $this;
		}

		$operations = $this->getOperations($operations);

		foreach ($operations as $operation) {
			call_user_func_array(array($this, $operation['function']), $operation['params']);
		}

		return $this;
	}



	/**
	 * private function getOperations (array $operations)
	 *
	 * Splits string operations and convert it to array
	 * Returns array
	 */
	private function getOperations ($operations) {
		$return = array();
		$operations = explode('|', str_replace(' ', '', $operations));

		foreach ($operations as $operations) {
			$params = explode(',', $operations);
			$function = trim(array_shift($params));

			if (!in_array($function, self::$operations)) {
				$this->setError('The transform function "'.$function.'" is not valid', IMAGECOW_ERROR_INPUT);
				continue;
			}

			$return[] = array(
				'function' => $function,
				'params' => $params
			);
		}

		return $return;
	}



	/**
	 * public function get ([string $image])
	 *
	 * Gets the image object
	 * Returns this
	 */
	public function get ($image = '') {
		if ($image) {
			if (!$this->load($image)) {
				return false;
			}
		}

		return $this->image;
	}



	/**
	 * public function set ([object $image])
	 *
	 * Sets the image object
	 * Returns this
	 */
	public function set ($image) {
		$this->image = $image;

		return $this;
	}



	/**
	 * public function show ([bool $header])
	 *
	 * Shows the image and die
	 */
	public function show ($header = true) {
		if ($string = $this->toString()) {
			if ($header && ($type = $this->getMimeType())) {
				header('Content-Type: '.$type);
			}

			die($string);
		}
	}



	/**
	 * protected function position (int/string $position, int $size, int $canvas)
	 *
	 * Calculates the x/y position of the image
	 * Returns integer
	 */
	protected function position ($position, $size, $canvas) {
		if (is_int($position)) {
			return $position;
		}

		switch ($position) {
			case 'top':
			case 'left':
				$position = 0;
				break;

			case 'middle':
			case 'center':
				$position = ($canvas/2) - ($size/2);
				break;

			case 'right':
			case 'bottom':
				$position = $canvas - $size;
				break;

			default:
				$position = $this->getSize($position, $canvas);
		}

		return $position;
	}


	/**
	 * public function resizeCrop (int $width, int $height, [int $x], [int $y])
	 *
	 * Crops an resize an image to specific dimensions
	 * Returns this
	 */
	public function resizeCrop ($width, $height, $x = 'center', $y = 'middle') {
		$width = $this->getSize($width, $this->getWidth());
		$height = $this->getSize($height, $this->getHeight());

		if (($width === 0) || ($height === 0)) {
			return false;
		}

		$width_resize = ($width / $this->getWidth()) * 100;
		$height_resize = ($height / $this->getHeight()) * 100;

		if ($width_resize < $height_resize) {
			$this->resize(0, $height);
		} else {
			$this->resize($width, 0);
		}

		$this->crop($width, $height, $x, $y);

		return $this;
	}


	/**
	 * protected function getSize (string $value, int $total_size)
	 *
	 * Calculates a dimmension size
	 * Returns integer
	 */
	protected function getSize ($value, $total_size) {
		if (substr($value, -1) === '%') {
			return ($total_size/100) * intval(substr($value, 0, -1));
		}

		return intval($value);
	}



	/**
	 * protected function enlarge ($width, $height, $image_width, $image_height)
	 *
	 * Calculate if the image must be enlarge or not
	 * Returns boolean
	 */
	protected function enlarge ($width, $height, $image_width, $image_height) {
		if ($width && $width > $image_width) {
			return true;
		}

		if ($height && $height > $image_height) {
			return true;
		}

		return false;
	}
}
?>