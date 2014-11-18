<?php
namespace Proffer\Model\Behavior;

use ArrayObject;
use Cake\Core\Plugin;
use Cake\Event\Event;
use Cake\Network\Exception\BadRequestException;
use Cake\ORM\Behavior;
use Cake\ORM\Entity;
use Cake\ORM\Table;
use Cake\Utility\String;
use Imagine\Image\Box;
use Imagine\Imagick\Imagine;

/**
 * Proffer behavior
 */
class ProfferBehavior extends Behavior {

/**
 * Default configuration.
 *
 * @var array
 */
	protected $_defaultConfig = [];

/**
 * beforeSave method
 *
 * @param Event $event
 * @param Entity $entity
 * @param ArrayObject $options
 * @return bool
 */
	public function beforeSave(Event $event, Entity $entity, ArrayObject $options) {
		/* @var \Cake\ORM\Table $table */
		$table = $event->subject();

		foreach ($this->config() as $field => $settings) {
			if ($entity->has($field) && is_array($entity->get($field)) && $entity->get($field)['error'] === UPLOAD_ERR_OK) {

				if (!is_uploaded_file($entity->get($field)['tmp_name'])) {
					throw new BadRequestException('File must be uploaded using HTTP post.');
				}

				$path = $this->buildPath($table, $entity, $field);

				if (move_uploaded_file($entity->get($field)['tmp_name'], $path['full'])) {
					$entity->set($field, $entity->get($field)['name']);
					$entity->set($settings['dir'], $path['parts']['seed']);

					$this->makeThumbs($field, $path);
				}
			}
		}

		return true;
	}

/**
 * Build a path to upload a file to. Both parts and full path
 *
 * @param Table $table
 * @param Entity $entity
 * @param $field
 * @return array
 */
	protected function buildPath(Table $table, Entity $entity, $field) {
		$path['root'] = WWW_ROOT . 'files';
		$path['table'] = strtolower($table->alias());

		if (!empty($entity->get($this->config($field)['dir']))) {
			$path['seed'] = $entity->get($this->config($field)['dir']);
		} else {
			$path['seed'] = String::uuid();
		}

		$path['name'] = $entity->get($field)['name'];

		$fullPath = implode(DS, $path);
		if (!file_exists($fullPath)) {
			mkdir($path['root'] . DS . $path['table'] . DS . $path['seed'] . DS, 0777, true);
		}

		return ['full' => $fullPath, 'parts' => $path];
	}

/**
 * Generate the defined thumbnails
 *
 * @param $field The name of the upload field
 * @param $path The path array
 * @return void
 */
	protected function makeThumbs($field, $path) {
		$imagine = new Imagine();
		$image = $imagine->open($path['full']);
		foreach ($this->config($field)['thumbnailSizes'] as $prefix => $thumbSize) {
			$filePath = $path['parts']['root'] . DS . $path['parts']['table'] . DS . $path['parts']['seed'] . DS . $prefix . '_' . $path['parts']['name'];

			// Both sizes, box resize
			if (isset($thumbSize['w']) && isset($thumbSize['h'])) {
				$image->resize(new Box($thumbSize['w'], $thumbSize['h']));
			} elseif (isset($thumbSize['w']) && !isset($thumbSize['h'])) {
				$image->resize($image->getSize()->widen($thumbSize['w']));
			} elseif (!isset($thumbSize['w']) && isset($thumbSize['h'])) {
				$image->resize($image->getSize()->heighten($thumbSize['h']));
			}

			$image->save($filePath);
		}
	}
}