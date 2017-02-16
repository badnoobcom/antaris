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

class PHPInfoServlet implements IServlet
{
	use Servlet;


	public function run()
	{
		$this->objHttpResponse->setBody($this->createBody());
	}


	public function getRoute(): string
	{
		return '/phpinfo';
	}


	private function createBody(): string
	{
		ob_start();
		?>
        <!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "DTD/xhtml1-transitional.dtd">
        <html xmlns="http://www.w3.org/1999/xhtml">
        <head>
            <style type="text/css">
                body {background-color: #ffffff; color: #000000;}
body, td, th, h1, h2 {font-family: sans-serif;}
pre {margin: 0px; font-family: monospace;}
a:link {color: #000099; text-decoration: none; background-color: #ffffff;}
a:hover {text-decoration: underline;}
table {border-collapse: collapse;}
.center {text-align: center;}
.center table { margin-left: auto; margin-right: auto; text-align: left;}
.center th { text-align: center !important; }
td, th { border: 1px solid #000000; font-size: 75%; vertical-align: baseline;}
h1 {font-size: 150%;}
h2 {font-size: 125%;}
.p {text-align: left;}
.e {background-color: #ccccff; font-weight: bold; color: #000000;}
.h {background-color: #9999cc; font-weight: bold; color: #000000;}
.v {background-color: #cccccc; color: #000000;}
.vr {background-color: #cccccc; text-align: right; color: #000000;}
img {float: right; border: 0px;}
hr {width: 600px; background-color: #cccccc; border: 0px; height: 1px; color: #000000;}
            </style>
            <title>phpinfo()</title>
            <meta name="ROBOTS" content="NOINDEX,NOFOLLOW,NOARCHIVE" />
        </head>
        <body>
        <div class="center">
            [CONTENT]
        </div>
        </body>
        </html>
		<?php
		$strBody = ob_get_clean();
		ob_start();
		phpinfo(-1);
		$strPhpinfo = ob_get_clean();

		/**
		 * YES! This is a mess :) and it just converts phpinfo's text output into HTML
		 */
		$arrPhpInfo = preg_split('/\\n/', $strPhpinfo);
		$strPhpinfo = '';
		$blnTableOpen = false;
		foreach($arrPhpInfo as $strCurrent)
        {
            $arrLine = explode(' => ', $strCurrent);
            if (count($arrLine) === 1)
            {
                if (strlen($strCurrent) === 0)
                {
                    if ($blnTableOpen)
					{
						$strPhpinfo .= '</table>';
						$blnTableOpen = false;
					}
                }
                else if(!$blnTableOpen)
				{
					$strPhpinfo .= '<h2>' . $strCurrent . '</h2><table width="600">';
					$blnTableOpen = true;
				}
				else
                {
                    $strPhpinfo .= '<tr><td class="h">'.$strCurrent.'</td></tr>';
                }
            }
            else
            {
                if (!$blnTableOpen)
                {
                    $blnTableOpen = true;
                    $strPhpinfo .= '<table width="600">';
                }

                $strPhpinfo .= '<tr>';
                foreach($arrLine as $intSplitIndex => $strSplitLine)
                {
                    if ($intSplitIndex === 0)
                    {
                        $strPhpinfo .= '<td class="e">';
                    }
                    else
                    {
                        $strPhpinfo .= '<td class="v">';
                    }
                    $strPhpinfo .= $strSplitLine.'</td>';
                }
                $strPhpinfo .= '</tr>';
            }
        }
		$strBody    = str_replace('[CONTENT]', $strPhpinfo, $strBody);

		return $strBody;
	}
}