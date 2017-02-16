<?php


namespace com\badnoob\antaris\io\http\request;


class MultipartFormDataParser
{
	/**
	 * @param \com\badnoob\antaris\io\http\request\HttpHeader $header
	 * @param string                                          $postData
	 *
	 * @return \com\badnoob\antaris\io\http\request\HttpFormDataElement[]
	 * @throws \HttpRequestException
	 */
	public function parseRequestBody(HttpHeader $header, string $postData): array
	{
		if(strpos($header->getValue(), HttpHeader::CONTENT_TYPE_MULTIPART_FORMDATA) === false)
		{
			throw new \HttpRequestException('Cannot get contents from form. Has to be '
											. HttpHeader::CONTENT_TYPE_MULTIPART_FORMDATA
											. ' but is '
											. $header->getValue());
		}

		// Fetch content and determine boundary
		$boundary = substr($postData, 0, strpos($postData, "\r\n"));

		if($boundary === '')
		{
			//this handles non-multipart form-data and need proper implementation!
			parse_str($postData, $data);

			return $data;
		}

		// Fetch each part
		$parts = array_slice(explode($boundary, $postData), 1);
		$data  = [];

		if(count($parts) === 0) throw new \HttpRequestException('No parts found in multipart/form-data!');

		/**
		 * This was found "on the internet" and needs some refurbishing ;)
		 */
		foreach($parts as $part)
		{
			// If this is the last part, break
			if($part === "--\r\n") break;

			// Separate content from headers
			$part = ltrim($part, "\r\n");
			list($raw_headers, $body) = explode("\r\n\r\n", $part, 2);

			// Parse the headers list
			$raw_headers = explode("\r\n", $raw_headers);
			$headers     = [];
			foreach($raw_headers as $contentHeader)
			{
				list($name, $value) = explode(':', $contentHeader);
				$value                        = trim($value);
				$headers[ strtolower($name) ] = $value;
			}

			if(!isset($headers['content-disposition']))
			{
				throw new \HttpRequestException('The content-disposition header could not be found in multipart/form-data.'
												. ' Headers:' . print_r($headers, true));
			}

			// Parse the Content-Disposition to get the field name, etc.
			if(isset($headers['content-disposition']))
			{
				$objElement = new HttpFormDataElement();

				$filename = null;
				$tmp_name = null;
				preg_match(
					'/^(.+); *name="([^"]+)"(; *filename="([^"]+)")?/',
					$headers['content-disposition'],
					$matches
				);
				list(, $type, $name) = $matches;
				$strLabel = trim($matches[2]);

				//Parse File
				if(isset($matches[4]))
				{
					//if labeled the same as previous, skip
					if($this->hasFileEntry($data, $strLabel)) continue;

					//get filename
					$filename = $matches[4];

					$objElement->setFileName($filename);
					$objElement->setBody($body);
				}
				//Parse Field
				else
				{
					$objElement = new HttpFormDataElement();
					$objElement->setBody(substr($body, 0, -2));
				}

				$objElement->setType($value);
				$objElement->setSize(strlen($body));
				$objElement->setLabel($strLabel);

				$data[] = $objElement;
			}

		}

		return $data;
	}


	/**
	 * @param HttpFormDataElement[] $data
	 * @param string                $label
	 *
	 * @return bool
	 */
	private function hasFileEntry(array $data, string $label)
	{
		/**
		 * @var HttpFormDataElement $objEntry
		 */
		foreach($data as $objEntry)
		{
			if($objEntry->getLabel() === $label) return true;
		}

		return false;
	}
}