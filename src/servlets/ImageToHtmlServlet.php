<?php
/**
 *          A    N    T    A    R    I    S
 * ====================================================
 * ======= an apache mod_jk <--> php connector ========
 * ====================================================
 *
 * @author Daniel Bunte <daniel.bunte@badnoob.com>
 */

namespace servlets;


use com\badnoob\antaris\core\servlet\IServlet;
use com\badnoob\antaris\core\servlet\Servlet;
use com\badnoob\antaris\io\http\request\HttpConstants;
use com\badnoob\antaris\utils\debugging\DebugPrinter;

class ImageToHtmlServlet implements IServlet
{
	use Servlet;


	public function getRoute(): string
	{
		return '/imagetohtml';
	}


	public function run()
	{
		$this->objHttpResponse->setBody($this->createBody());
	}


	/**
	 * @return string
	 */
	private function createBody(): string
	{
		$strResourcesDir = __DIR__ . '/../webapps/resources';
		$arrColorTable = [];

		if($this->objHttpRequest->getMethod() === HttpConstants::METHOD_NAME_GET)
		{
			$strResponse = file_get_contents($strResourcesDir . '/html/image2html_upload.content.html');
		}
		else
		{
			$strResponse   = file_get_contents($strResourcesDir . '/html/image2html_result.content.html');
			$strContent    = '';
			$strTag        = 'div';
			$strPrevCSSKey = '';
			$intRow        = 0;

			list($objIterator, $tmpFile) = $this->prepareAndSaveTemporaryImage();

			/**
			 * @var $objRow
			 * @var $objIterator \ImagickPixelIterator
			 */
			foreach($objIterator as $objRow => $objPixels)
			{
				$strContent .= '<div class="row" name="row_' . $intRow . '">';
				$strCurrentTag = $this->prepareTag($strTag);
				$intWidth      = 0;

				/**
				 * @var $objPixel  \ImagickPixel
				 * @var $objPixels \ImagickPixelIterator
				 */
				foreach($objPixels as $objColumn => $objPixel)
				{
					$arrColor = $objPixel->getColor();

					// we don't want to process alpha
					unset($arrColor['a']);

					$strCSSKey = $this->populateColorTableAndGetCSSKey($arrColor, $arrColorTable);

					if($strPrevCSSKey !== '' && $strPrevCSSKey !== $strCSSKey)
					{
						$strContent .= $this->finalizeTag($strPrevCSSKey, $intWidth, $strCurrentTag, $strTag);
						$intWidth      = 1;
						$strPrevCSSKey = $strCSSKey;
						continue;
					}

					$strPrevCSSKey = $strCSSKey;

					++$intWidth;
				}

				$strContent .= $this->finalizeTag($strPrevCSSKey, $intWidth, $strCurrentTag, $strTag) . '</div>';
				$strPrevCSSKey = '';
				++$intRow;
			}

			$arrPlaceholders = [
				'[CONTENT]',
				'[PARTS]'
			];

			$strParts = print_r($this->objHttpRequest->getMultipartElementsFromPOST(), true);
			$strParts = preg_replace('/ /m', '&nbsp;', $strParts);
			$strParts = nl2br($strParts);

			$arrReplacements = [
				$strContent,
				$strParts
			];
			$strResponse     = str_replace($arrPlaceholders, $arrReplacements, $strResponse);
			unlink($tmpFile);
		}

		$strTemplatePath = '/html/template.html';

		$strTemplate = file_get_contents($strResourcesDir . $strTemplatePath);

		if($strTemplate === false)
		{
			throw new ServletException('file template.html could not be found in resources directory '
									   . $strTemplatePath);
		}

		$strBody = str_replace([
								   '[MAINCONTENT]',
								   '/**image2htmldummy**/'
							   ],
							   [
								   $strResponse,
								   $this->getCSSColorTable($arrColorTable)
							   ],
							   $strTemplate);

		return $strBody;
	}


	/**
	 * @param $arrColor
	 * @param $arrColorTable
	 *
	 * @return string
	 */
	private function populateColorTableAndGetCSSKey($arrColor, &$arrColorTable)
	{
		$strColorCode = substr(implode('', $arrColor), 0, -1); // remove alpha
		if(!array_key_exists($strColorCode, $arrColorTable))
		{
			$intCSSKey = count($arrColorTable);
			$strHex    = '';
			foreach($arrColor as $key => $value)
			{
				$strHexValue = dechex($value);
				if(strlen($strHexValue) < 2)
				{
					$strHexValue = '0' . $strHexValue;
				}

				$strHex .= $strHexValue;
			}
			$arrColorTable[ $strColorCode ] = [
				$intCSSKey,
				'#_'
				. $intCSSKey
				. '{background-color:#'
				. $strHex
				. '}'
			];

			return $intCSSKey;
		}

		return $arrColorTable[ $strColorCode ][0];
	}


	/**
	 * @param $objImage   \Imagick
	 * @param $intMaxSize int
	 */
	private function resizeImage(\Imagick $objImage, $intMaxSize)
	{
		if($objImage->getImageWidth() > $intMaxSize || $objImage->getImageHeight() > $intMaxSize)
		{
			$intWidth  = min($objImage->getImageWidth(), $intMaxSize);
			$intHeight = min($objImage->getImageHeight(), $intMaxSize);
			if($intWidth > $intHeight)
			{
				$objImage->resizeImage(0, $intMaxSize, \Imagick::FILTER_GAUSSIAN, 1);
			}
			else
			{
				$objImage->resizeImage($intWidth, 0, \Imagick::FILTER_GAUSSIAN, 1);
			}
		}
	}


	/**
	 * @param $strTag
	 *
	 * @return string
	 */
	private function prepareTag($strTag): string
	{
		return '<' . $strTag . ' id="_#ID"#WIDTH>';
	}


	/**
	 * @param $intWidth
	 *
	 * @return string
	 */
	private function getWidthReplacement($intWidth): string
	{
		return $intWidth > 1 ? ' style="width:' . $intWidth . 'px"' : '';
	}


	/**
	 * @param $arrColorTable array
	 *
	 * @return string
	 */
	private function getCSSColorTable($arrColorTable): string
	{
		$strColorTable = '';
		foreach($arrColorTable as $objItem)
		{
			$strColorTable .= $objItem[1];
		}

		return $strColorTable;
	}


	/**
	 * @param $strCSSKey
	 * @param $intWidth
	 * @param $strCurrentTag
	 * @param $strTag
	 *
	 * @return string
	 */
	private function finalizeTag($strCSSKey, $intWidth, $strCurrentTag, $strTag): string
	{
		return str_replace(['#ID', '#WIDTH'], [$strCSSKey, $this->getWidthReplacement($intWidth)], $strCurrentTag)
			   . '</' . $strTag . '>';
	}


	/**
	 * @return array
	 */
	private function prepareAndSaveTemporaryImage(): array
	{
		$objFile      = $this->objHttpRequest->getMultipartElementsFromPOST()[0];
		$intMaxColors = $this->objHttpRequest->getMultipartElementByLabel('maxcolors')->getBody();
		$intMaxSize   = $this->objHttpRequest->getMultipartElementByLabel('maxsize')->getBody();
		DebugPrinter::debug('maxcolors:' . $intMaxColors . ', maxsize:' . $intMaxSize);
		$tmpFile = '/tmp/' . md5($objFile->getBody());
		file_put_contents($tmpFile, $objFile->getBody());

		$objImage = new \Imagick($tmpFile);
		$this->resizeImage($objImage, $intMaxSize);
		$objImage->quantizeImage($intMaxColors, \Imagick::COLORSPACE_SRGB, 0, true, true);

		return [$objImage->getPixelIterator(), $tmpFile];
	}
}