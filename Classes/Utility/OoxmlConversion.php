<?php

namespace Tpwd\KeSearch\Utility;

/**
 * Class OoxmlConversion
 *
 * @see https://stackoverflow.com/questions/19503653/how-to-extract-text-from-word-file-doc-docx-xlsx-pptx-php
 */
class OoxmlConversion
{
    /**
     * @var string
     */
    private $filename;

    /**
     * OoxmlConversion constructor
     *
     * @param string $filePath
     * @throws \Exception If given filePath is not existing
     */
    public function __construct($filePath)
    {
        if (!file_exists($filePath)) {
            throw new \Exception('File "' . $filePath . '" not found!');
        }
        $this->filename = $filePath;
    }

    /**
     * Read text contents from Word files (DOCX)
     *
     * @return string|bool
     */
    private function readDocx()
    {
        $content = '';
        $zip = new \ZipArchive();

        if ($zip->open($this->filename) !== true) {
            return false;
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $zipEntry = $zip->getNameIndex($i);

            if ($zipEntry !== 'word/document.xml') {
                continue;
            }

            $content .= ($zip->getFromName($zipEntry) ?: '');
        }

        $zip->close();

        $content = str_replace('</w:r></w:p></w:tc><w:tc>', ' ', $content);
        $content = str_replace('</w:r></w:p>', "\r\n", $content);

        return strip_tags($content);
    }

    /**
     * Read text contents from Excel file (XLSX)
     * @return string
     */
    private function readXlsx()
    {
        $zipHandle = new \ZipArchive();
        $outputText = '';
        if ($zipHandle->open($this->filename) === true) {
            if (($xmlIndex = $zipHandle->locateName('xl/sharedStrings.xml')) !== false) {
                $xmlData = $zipHandle->getFromIndex($xmlIndex);
                $domDocument = new \DOMDocument();
                $domDocument->loadXML($xmlData, LIBXML_NOENT | LIBXML_XINCLUDE | LIBXML_NOERROR | LIBXML_NOWARNING);
                $outputText = strip_tags(str_replace('</t>', ' </t>', $domDocument->saveXML()));
            } else {
                $outputText .= '';
            }
            $zipHandle->close();
        } else {
            $outputText .= '';
        }
        return $outputText;
    }

    /**
     * Read text contents from Powerpoint file (PPTX)
     *
     * @return string
     */
    private function readPptx()
    {
        $zipHandle = new \ZipArchive();
        $outputText = '';
        if ($zipHandle->open($this->filename) === true) {
            $slideNumber = 1; //loop through slide files
            while (($xml_index = $zipHandle->locateName('ppt/slides/slide' . $slideNumber . '.xml')) !== false) {
                $xmlData = $zipHandle->getFromIndex($xml_index);
                $domDocument = new \DOMDocument();
                $domDocument->loadXML($xmlData, LIBXML_NOENT | LIBXML_XINCLUDE | LIBXML_NOERROR | LIBXML_NOWARNING);
                $outputText .= strip_tags(str_replace('</a:t>', ' </a:t>', $domDocument->saveXML()));
                $slideNumber++;
            }
            if ($slideNumber === 1) {
                $outputText .= ' ';
            }
            $zipHandle->close();
        } else {
            $outputText .= ' ';
        }
        return $outputText;
    }

    /**
     * Extract text from given OOXML file
     *
     * @return string
     * @throws \InvalidArgumentException
     */
    public function convertToText()
    {
        $pathInfo = pathinfo($this->filename);
        switch (strtolower($pathInfo['extension'])) {
            case 'docx':
                return $this->readDocx();
            case 'xlsx':
                return $this->readXlsx();
            case 'pptx':
                return $this->readPptx();

            default:
                throw new \InvalidArgumentException('File extension "' . $pathInfo['extension'] . '" not supported!');
        }
    }
}
