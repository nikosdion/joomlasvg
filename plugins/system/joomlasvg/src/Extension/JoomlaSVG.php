<?php
/*
 * @package   JoomlaSVGSupport
 * @copyright Copyright (c)2020-2022 Nicholas K. Dionysopoulos
 * @license   GNU General Public License version 3, or later
 */

namespace Joomla\Plugin\System\Joomlasvg\Extension;

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Helper\MediaHelper;
use Joomla\CMS\Image\Image;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Utility\BufferStreamHandler;
use Joomla\Component\Banners\Site\Helper\BannerHelper;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;

/**
 * Add SVG support to Joomla 4
 *
 * @package     JoomlaSVGSupport
 *
 * @since       1.0.0
 */
class JoomlaSVG extends CMSPlugin implements SubscriberInterface
{
	/**
	 * Is this an SVG file?
	 *
	 * @param   string  $filePath  The path to check
	 *
	 * @return  bool  True if it's an SVG file
	 *
	 * @since   2.0.0
	 */
	public static function isSVG(string $filePath): bool
	{
		if (strtolower(substr($filePath, -4)) !== '.svg')
		{
			return false;
		}

		if (!function_exists('mime_content_type'))
		{
			return false;
		}

		$mime = strtolower(mime_content_type($filePath));

		return in_array($mime, ['image/svg+xml', 'application/svg+xml']);
	}

	public static function getSubscribedEvents(): array
	{
		return [
			'onAfterInitialise' => 'onAfterInitialise',
		];
	}

	/**
	 * Executed when Joomla boots up. Used to do in-memory patching of the core files involved in SVG support.
	 *
	 * @since        1.0.0
	 * @noinspection PhpUnused
	 */
	public function onAfterInitialise(Event $e)
	{
		require_once __DIR__ . '/../Buffer.php';

		$this->loadLanguage();

		// This patches the MediaHelper to add SVG preview support to the Media Manager *AND* sanitize SVGs
		if (!class_exists(MediaHelper::class, false))
		{
			$this->patchMediaHelper();
		}

		// This patches BannerHelper to add SVG support to Banners.
		if (!class_exists(BannerHelper::class, false))
		{
			$this->patchBannerHelper();
		}

		if (!class_exists(Image::class, false))
		{
			$this->patchImage();
		}
	}

	/**
	 * In-memory patching of the MediaHelper core helper file.
	 *
	 * @since  1.0.0
	 */
	private function patchMediaHelper()
	{
		$source = JPATH_LIBRARIES . '/src/Helper/MediaHelper.php';

		$phpContent = file_get_contents($source);
		$phpContent = str_replace('\'xcf|odg|gif|jpg|jpeg|png|bmp|webp\'', "'" . $this->getImageExtensionsPipe() . "'", $phpContent);

		BufferStreamHandler::stream_register();

		$bufferLocation = 'plgSystemJoomlaSVGBuffer://plgSystemJoomlaSVGMediaHelper.php';

		file_put_contents($bufferLocation, $phpContent);
		require_once $bufferLocation;
	}

	/**
	 * In-memory patching of the BannerHelper core helper file.
	 *
	 * @since  1.0.0
	 */
	private function patchBannerHelper()
	{
		$source = JPATH_SITE . '/components/com_banners/src/Helper/BannerHelper.php';

		$phpContent = file_get_contents($source);
		$phpContent = str_replace('bmp|gif|jpe?g|png|webp', $this->getImageExtensionsPipe(), $phpContent);

		BufferStreamHandler::stream_register();

		$bufferLocation = 'plgSystemJoomlaSVGBuffer://plgSystemJoomlaSVGBannerHelper.php';

		file_put_contents($bufferLocation, $phpContent);

		require_once $bufferLocation;
	}

	/**
	 * In-memory patching of the core Image helper file.
	 *
	 * @since  2.0.0
	 */
	private function patchImage()
	{
		$source      = JPATH_SITE . '/libraries/src/Image/Image.php';
		$phpContent  = file_get_contents($source);
		$replaceWith = <<< PHP
if (\Joomla\Plugin\System\Joomlasvg\Extension\JoomlaSVG::isSVG(\$path)) {
	return (object) [
		'width'       => 1024,
		'height'      => 1024,
		'type'        => IMAGETYPE_UNKNOWN,
		'attributes'  => 'height="60" width="60"',
		'bits'        => null,
		'channels'    => null,
		'mime'        => 'image/svg+xml',
		'filesize'    => filesize(\$path),
		'orientation' => 'square',
	];
}

\$info = getimagesize(\$path);
PHP;
		$phpContent  = str_replace('$info = getimagesize($path);', $replaceWith, $phpContent);

		BufferStreamHandler::stream_register();

		$bufferLocation = 'plgSystemJoomlaSVGBuffer://plgSystemJoomlaSVGImage.php';

		file_put_contents($bufferLocation, $phpContent);

		require_once $bufferLocation;
	}

	/**
	 * Get the image extensions from the Media Manager configuration
	 *
	 * @return  string
	 *
	 * @since   2.0.0
	 */
	private function getImageExtensionsPipe(): string
	{
		$mediaParams = ComponentHelper::getParams('com_media');
		$extensions  = $mediaParams->get('image_extensions', 'bmp,gif,jpg,jpeg,png,webp');
		$extensions  = array_map(
			'trim',
			explode(',', $extensions)
		);
		$extensions  = array_merge($extensions, ['svg', 'webp', 'gif', 'jpg', 'jpeg', 'png', 'bmp']);

		$extensions[] = 'svg';
		$extensions[] = 'SVG';
		$extensions   = array_unique($extensions);

		return implode('|', $extensions);
	}
}