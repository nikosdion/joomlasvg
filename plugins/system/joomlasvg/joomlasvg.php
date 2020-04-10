<?php
/**
 * @package   JoomlaSVGSupport
 * @copyright Copyright (c)2020-2020 Nicholas K. Dionysopoulos
 * @license   GNU General Public License version 3, or later
 */

defined('_JEXEC') or die;

use enshrined\svgSanitize\Sanitizer;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Component\ComponentRecord;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Utility\BufferStreamHandler;

/**
 * Add SVG support to Joomla 3
 *
 * @package     JoomlaSVGSupport
 *
 * @since       1.0.0
 */
class plgSystemJoomlasvg extends CMSPlugin
{
	/**
	 * Tries to sanitize an uploaded file if it's an SVG file (by extension or MIME type)
	 *
	 * @param   array  $fileDefinition  The uploaded file definition to sanitize
	 *
	 * @throws  RuntimeException  In case of an error
	 * @since   1.0.0
	 *
	 */
	public static function sanitizeSVG(array $fileDefinition): void
	{
		$intendedName = $fileDefinition['name'];
		$tempName     = $fileDefinition['tmp_name'];

		// We only deal with uploaded SVGs here
		if (self::isSVG($intendedName, $tempName))
		{
			return;
		}

		require_once __DIR__ . '/vendor/autoload.php';

		$sanitizer = new Sanitizer();
		$sanitizer->removeRemoteReferences(true);
		$sanitizer->minify(true);

		// Load the dirty svg
		$dirtySVG = @file_get_contents($tempName);

		if ($dirtySVG === false)
		{
			throw new RuntimeException(Text::_('PLG_SYSTEM_JOOMLASVG_ERR_UPLOAD_DISAPPEARED'));
		}

		// Pass it to the sanitizer and get it back clean
		$cleanSVG = $sanitizer->sanitize($dirtySVG);

		if ($cleanSVG === false)
		{
			throw new RuntimeException(Text::_('PLG_SYSTEM_JOOMLASVG_ERR_INVALID_FILE'));
		}

		file_put_contents($tempName, $cleanSVG);
	}

	/**
	 * Overrides Joomla's image information for SVG files, preventing Joomla notices.
	 *
	 * @param   string  $filePath  The path to the image file to get information from
	 *
	 * @return  array|false  Results of getimagesize or faked (but correct!) ones for SVGs
	 *
	 * @since   1.0.0
	 */
	public static function getimagesize($filePath)
	{
		if (!self::isSVG($filePath, $filePath))
		{
			return @getimagesize($filePath);
		}

		return [
			60,
			60,
			'svg',
			'image/svg+xml'
		];
	}

	/**
	 * Is this an SVG file?
	 *
	 * @param   string  $intendedName  The name under which the file will be uploaded e.g. foobar.svg
	 * @param   string  $tempPath      The temporary uploaded path e.g. /tmp/php/upload01234
	 *
	 * @return  bool  True if it's an SVG file
	 *
	 * @since   1.0.0
	 */
	private static function isSVG(string $intendedName, string $tempPath): bool
	{
		if (strtolower(substr($intendedName, -4)) === '.svg')
		{
			return true;
		}

		// TODO Better MIME type detection?
		if (!function_exists('mime_content_type'))
		{
			return false;
		}

		$mime = strtolower(mime_content_type($tempPath));

		return in_array($mime, ['image/svg+xml', 'application/svg+xml']);
	}

	/**
	 * Executed when Joomla boots up. Used to do in-memory patching of the core files involved in SVG support.
	 *
	 * @since   1.0.0
	 */
	public function onAfterInitialise()
	{
		require_once __DIR__ . '/buffer.php';

		$this->loadLanguage();

		// This patches the MediaModelList to add SVG upload support to the Media Manager
		if (!class_exists('MediaModelList', false))
		{
			$this->patchMediaModelList();
		}

		// This patches the MediaHelper to add SVG preview support to the Media Manager *AND* sanitize SVGs
		if (!class_exists('Joomla\CMS\Helper\MediaHelper', false))
		{
			$this->patchMediaHelper();
		}

		// Patch com_media options to allow uploading of SVG files
		try
		{
			$this->patchComMediaOptions();
		}
		catch (ReflectionException $e)
		{
			// Oops. You'll have to do it yourself.
		}
	}

	/**
	 * In-memory patching of the MediaModelList core model file.
	 *
	 * @since  1.0.0
	 */
	private function patchMediaModelList()
	{
		$source     = JPATH_ADMINISTRATOR . '/components/com_media/models/list.php';
		$foobar     = <<< PHP
\$info = \\plgSystemJoomlasvg::getimagesize(\$tmp->path);
PHP;
		$phpContent = file_get_contents($source);
		$phpContent = str_replace('case \'jpg\':', "case 'jpg':\ncase 'svg':", $phpContent);
		$phpContent = str_replace('$info = @getimagesize($tmp->path);', $foobar, $phpContent);

		BufferStreamHandler::stream_register();

		$bufferLocation = 'plgSystemJoomlaSVGBuffer://plgSystemJoomlaSVGMediaModelList.php';

		file_put_contents($bufferLocation, $phpContent);

		require_once $bufferLocation;
	}

	/**
	 * In-memory patching of the MediaHelper core helper file.
	 *
	 * @since  1.0.0
	 */
	private function patchMediaHelper()
	{
		$source           = JPATH_LIBRARIES . '/src/Helper/MediaHelper.php';
		$sanitizationCode = <<< PHP
try {
	\\plgSystemJoomlasvg::sanitizeSVG(\$file);
} catch (RuntimeException \$e) {
	\$app->enqueueMessage(\$e->getMessage(), 'error');

	return false;
}

\$images = array_map('trim', explode(',', \$params->get('image_extensions')));
PHP;


		$phpContent = file_get_contents($source);
		$phpContent = str_replace('\'xcf|odg|gif|jpg|png|bmp\'', "'xcf|odg|gif|jpg|png|bmp|svg'", $phpContent);
		$phpContent = str_replace('$images = array_map(\'trim\', explode(\',\', $params->get(\'image_extensions\')));', $sanitizationCode, $phpContent);

		BufferStreamHandler::stream_register();

		$bufferLocation = 'plgSystemJoomlaSVGBuffer://plgSystemJoomlaSVGMediaHelper.php';

		file_put_contents($bufferLocation, $phpContent);
		require_once $bufferLocation;
	}

	/**
	 * Patches the com_media options to allow uploading SVG files
	 *
	 * @throws  ReflectionException
	 * @since   1.0.0
	 */
	private function patchComMediaOptions(): void
	{
		ComponentHelper::getParams('com_media');

		$refClass = new ReflectionClass('\Joomla\CMS\Component\ComponentHelper');
		$prop     = $refClass->getProperty('components');
		$prop->setAccessible(true);
		$allComponents = $prop->getValue();
		/** @var ComponentRecord $comMedia */
		$comMedia = $allComponents['com_media'];

		$value = $comMedia->getParams()->get('upload_extensions', 'bmp,csv,doc,gif,ico,jpg,jpeg,odg,odp,ods,odt,pdf,png,ppt,txt,xcf,xls,BMP,CSV,DOC,GIF,ICO,JPG,JPEG,ODG,ODP,ODS,ODT,PDF,PNG,PPT,TXT,XCF,XLS');
		$value .= ",SVG,svg";
		$comMedia->getParams()->set('upload_extensions', $value);

		$value = $comMedia->getParams()->get('image_extensions', 'bmp,gif,jpg,png');
		$value .= ',svg';
		$comMedia->getParams()->set('image_extensions', $value);

		$value = $comMedia->getParams()->get('upload_mime', 'image/jpeg,image/gif,image/png,image/bmp,application/msword,application/excel,application/pdf,application/powerpoint,text/plain,application/x-zip');
		$value .= ',image/svg+xml,application/svg+xml';
		$comMedia->getParams()->set('upload_mime', $value);

		$allComponents['com_media'] = $comMedia;

		$prop->setValue($allComponents);
	}
}